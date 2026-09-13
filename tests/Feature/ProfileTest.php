<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    /**
     * Issue #27: changing the password must end every other active session
     * (NIST SP 800-63B) and reject outstanding remember-me cookies. These
     * tests force SESSION_DRIVER=database for the request so session rows
     * exist; phpunit.xml runs the suite on the array driver otherwise.
     */
    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    private function insertSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_password_change_invalidates_other_sessions_and_remembers_nothing(): void
    {
        $this->useDatabaseSessions();
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Two attacker sessions for our user, one innocent bystander session.
        $this->insertSession('attacker-session-1', $user->id);
        $this->insertSession('attacker-session-2', $user->id);
        $this->insertSession('bystander-session', $other->id);

        $response = $this
            ->actingAs($user)
            ->post('/profile/change-password', [
                'current_password' => 'password',
                'new_password' => 'new-secret-password',
                'new_password_confirmation' => 'new-secret-password',
            ]);

        $response->assertSessionHasNoErrors();

        // The hijacker's sessions are gone...
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session-2']);
        // ...but nobody else's sessions were collateral damage...
        $this->assertDatabaseHas('sessions', ['id' => 'bystander-session']);
        // ...and the device that made the change stayed signed in.
        $currentSessionId = session()->getId();
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId, 'user_id' => $user->id]);
        $this->assertAuthenticatedAs($user);

        // A remember-me cookie from before the change must not re-authenticate.
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_wrong_current_password_leaves_sessions_untouched(): void
    {
        $this->useDatabaseSessions();
        $user = User::factory()->create();
        $this->insertSession('still-valid-session', $user->id);

        $response = $this
            ->actingAs($user)
            ->post('/profile/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'new-secret-password',
                'new_password_confirmation' => 'new-secret-password',
            ]);

        $response->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('sessions', ['id' => 'still-valid-session', 'user_id' => $user->id]);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    /**
     * Issue #191: `password_reset_tokens` is keyed by email, so the #48 FK
     * cascades and the #53/#57/#61/#115 model sweeps all miss it — a token
     * minted before account deletion outlived the account. These pin both
     * halves: the row is gone after /profile deletion, and the deletion (not
     * just the sweep) closes the takeover path for a recycled email address.
     */
    public function test_account_deletion_sweeps_password_reset_tokens(): void
    {
        $user = User::factory()->create();
        Password::broker()->createToken($user);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_token_from_a_deleted_account_cannot_take_over_a_reregistered_email(): void
    {
        $victim = User::factory()->create(['email' => 'recycled@example.com']);
        $token = Password::broker()->createToken($victim);

        $this->actingAs($victim)->delete('/profile', ['password' => 'password']);

        // The address is free again, and a newcomer registers on it.
        $this->post('/register', [
            'name' => 'Newcomer',
            'email' => 'recycled@example.com',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertSessionHasNoErrors();

        // Registration auto-logs-in, and /reset-password sits in the guest
        // group — a real takeover is attempted from a guest session.
        $this->post('/logout');

        // The dead account's stale token must not reset the newcomer: the
        // broker looks up password_reset_tokens by email, so without the
        // sweep this POST succeeded and rewrote the new user's password.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'recycled@example.com',
            'password' => 'pwned-password',
            'password_confirmation' => 'pwned-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('brand-new-secret', User::where('email', 'recycled@example.com')->value('password')));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'recycled@example.com']);
    }

    /**
     * Issue #203: #191's twin — the account SURVIVES an email change, so the
     * deleting hook never fires, yet password_reset_tokens is keyed by email.
     * A token minted for the old address stayed live after the move; once the
     * abandoned address was free, a fresh registration on it was resolved by
     * the broker into the stale token and POST /reset-password rewrote the
     * newcomer's password. Pin both halves: the row dies with the address
     * (and only the OLD address), and the full takeover chain through real
     * routes closes.
     */
    public function test_email_change_sweeps_password_reset_tokens_of_the_old_address(): void
    {
        $user = User::factory()->create(['email' => 'moving@example.com']);
        $token = Password::broker()->createToken($user);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'moving@example.com']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'moved@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'moving@example.com']);
    }

    public function test_reset_token_from_before_an_email_change_cannot_take_over_the_abandoned_address(): void
    {
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $token = Password::broker()->createToken($alice);

        $this->actingAs($alice)
            ->patch('/profile', ['name' => $alice->name, 'email' => 'alice-new@example.com'])
            ->assertSessionHasNoErrors();

        // actingAs persists across test requests; /register is a guest route,
        // so the mailbox-change must end Alice's session before Bob's fresh
        // registration (in the wild these are two different browsers).
        $this->post('/logout');

        // Alice's new address is hers; the abandoned one is free, and Bob
        // registers it.
        $this->post('/register', [
            'name' => 'Bob',
            'email' => 'alice@example.com',
            'password' => 'bobs-secret-password',
            'password_confirmation' => 'bobs-secret-password',
        ])->assertSessionHasNoErrors();

        // Register auto-logs-in; /reset-password is a guest route.
        $this->post('/logout');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'alice@example.com',
            'password' => 'pwned-password',
            'password_confirmation' => 'pwned-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('bobs-secret-password', User::where('email', 'alice@example.com')->value('password')));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'alice@example.com']);
    }

    public function test_email_change_keeps_reset_tokens_for_addresses_not_left_behind(): void
    {
        // Control: the sweep is scoped to the OLD address, not everything.
        // A live token for an unrelated mailbox must survive the change.
        $user = User::factory()->create(['email' => 'pair-a@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => 'pair-b@example.com',
            'token' => 'irrelevant-but-live',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'pair-c@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'pair-b@example.com']);
    }
}
