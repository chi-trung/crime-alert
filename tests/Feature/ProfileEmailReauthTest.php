<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #253: PATCH /profile could MOVE the account's recovery address with
 * no credential and no rotation. forgot/reset-password are guest routes
 * keyed purely on users.email with no verified requirement, so one request
 * from a hijacked session (stored XSS is this repo's own history — #2, #18)
 * repointed the mailbox; the attacker then requested a link as a plain guest
 * and POST /reset-password forceFilled a password the owner can never type
 * again — permanent takeover. The fix demands current_password exactly when
 * the submitted email differs from the stored one (mirroring #154's
 * string+bail credential shape) and applies changePassword()'s
 * #27/#123/#204 rotation (remember token + other sessions + new address's
 * token residue) when the move lands. #203 already swept the OLD address.
 */
class ProfileEmailReauthTest extends TestCase
{
    use RefreshDatabase;

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    public function test_an_email_change_without_the_password_is_rejected_and_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'attacker@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('owner@example.com', $user->fresh()->email,
            'the recovery door must not move without re-authentication');
        $this->assertNotNull($user->fresh()->email_verified_at,
            'a rejected change must not even un-verify the old address');
    }

    public function test_an_email_change_with_the_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => 'guess',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('owner@example.com', $user->fresh()->email);
    }

    public function test_a_name_only_change_still_needs_no_password(): void
    {
        // The gate is scoped to the email transition: renaming under a
        // same-address payload keeps the stock Breeze contract.
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->patch('/profile', ['name' => 'Renamed', 'email' => 'owner@example.com'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertSame('owner@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at,
            'the address did not move, so #129 stays untouched');
    }

    public function test_a_confirmed_email_change_rotates_the_credential_surface(): void
    {
        $this->useDatabaseSessions();
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create();

        // Attacker artifacts exactly as PasswordUpdateTest plants them: a
        // second live session row for our user and a planted remember token.
        DB::table('sessions')->insert([
            'id' => 'attacker-session',
            'user_id' => $user->id,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'curl/8',
            'payload' => 'stolen',
            'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'bystander-session',
            'user_id' => $other->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
        $user->forceFill(['remember_token' => 'ATTACKER_REMEMBER_COOKIE'])->save();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'moved@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertSame('moved@example.com', $fresh->email);
        $this->assertNull($fresh->remember_token, '#27: a stolen remember cookie must die with the move');
        // NB: assertDatabase* takes a connection as its second argument, not
        // a message — count through DB::table so the failure can explain itself.
        $this->assertSame(0, DB::table('sessions')->where('id', 'attacker-session')->count(),
            '#27: other sessions end');
        $this->assertSame(1, DB::table('sessions')->where('id', 'bystander-session')->count(),
            'nobody else is collateral');
        $this->assertSame(1, DB::table('sessions')->where('id', session()->getId())->count(),
            'the confirming device stays signed in');
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_a_rejected_email_change_leaves_the_credential_surface_alone(): void
    {
        $this->useDatabaseSessions();
        $user = User::factory()->create(['email' => 'owner@example.com']);
        DB::table('sessions')->insert([
            'id' => 'still-valid-session',
            'user_id' => $user->id,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'curl/8',
            'payload' => 'stolen',
            'last_activity' => time(),
        ]);
        $user->forceFill(['remember_token' => 'ATTACKER_REMEMBER_COOKIE'])->save();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => 'wrong',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('sessions', ['id' => 'still-valid-session']);
        $this->assertSame('ATTACKER_REMEMBER_COOKIE', $user->fresh()->remember_token,
            'the rotation belongs to a LANDED move, not an attempted one');
    }

    public function test_the_reset_door_for_the_new_address_is_swept_on_a_confirmed_move(): void
    {
        // #191's doctrine mirrored: an email moving INTO the account must not
        // inherit a reset link minted while the mailbox pointed elsewhere (or
        // in the window before this change). The OLD address's sweep is #203
        // and already pinned in ProfileTest; this pins the NEW side.
        $user = User::factory()->create(['email' => 'owner@example.com']);
        DB::table('password_reset_tokens')->insert([
            'email' => 'vacant@example.com',
            'token' => Hash::make('stale-link'),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'vacant@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'vacant@example.com')->count(),
            'a pre-move link must not seize the account that just moved in');
    }

    public function test_the_full_takeover_chain_now_breaks_at_the_profile_step(): void
    {
        // End-to-end, real routes: hijacked session -> try to repoint the
        // mailbox. Without the password the chain dies at step 1; the
        // attacker-as-guest reset door is then powerless because users.email
        // never moved. Proves the ISSUE's failure mode, not just the rule.
        $victim = User::factory()->create(['email' => 'victim@example.com']);

        $this->actingAs($victim)
            ->patch('/profile', ['name' => $victim->name, 'email' => 'evil@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->post('/logout');

        // The attacker, now a guest, requests recovery on the address they
        // TRIED to install — it belongs to nobody, so the broker says so.
        // (The victim side of the door — recovery still reaching the real
        // mailbox after a landed move — is pinned by ProfileTest's #204
        // controls; a second forgot POST here would only trip the route
        // throttle's per-IP bucket.)
        $this->post('/forgot-password', ['email' => 'evil@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertSame('victim@example.com', $victim->fresh()->email);
    }

    public function test_array_email_still_rejects_without_a_500_under_the_new_gate(): void
    {
        // #160's crafted input must not trip the rules()-time comparison:
        // is_scalar guards the (string) cast before validation runs.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => ['x@y.com']])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }
}
