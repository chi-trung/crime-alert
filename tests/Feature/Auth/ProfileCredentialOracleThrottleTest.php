<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #180 put 5/min lanes on /confirm-password and /profile/change-password
 * and called them "the two live password-verification oracles" — but the sweep
 * stopped at routes/auth.php. PATCH /profile carries the same oracle one door
 * over: ProfileUpdateRequest runs 'current_password' whenever the submitted
 * email differs from the stored one, so a wrong guess is a clean 302 with
 * errors.current_password and a right one moves the recovery address — the
 * exact #253 takeover primitive, grindable at network speed with no limiter.
 * DELETE /profile (#154's validateWithBag('userDeletion', ... 'current_password'))
 * is the same shape: wrong = 302 + userDeletion error bag, right = the account
 * and all its rows gone. Neither route had a throttle at all.
 *
 * Both get 5/min in their own lanes ('profile-update', 'profile-destroy') so
 * per #147 they share no counter with each other, #180's oracle lanes, #33's
 * guest lanes or #165's content lanes.
 */
class ProfileCredentialOracleThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => 'Correct-Horse-1!']);
    }

    public function test_the_profile_update_oracle_locks_out_at_five_guesses_per_minute(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $originalEmail = $user->email;

        for ($i = 1; $i <= 5; $i++) {
            $this->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => "wrong-{$i}",
            ])->assertSessionHasErrors('current_password');
        }

        // Pre-fix: this 6th guess was answered like the first five — an
        // unlimited oracle on the recovery address.
        $this->patch('/profile', [
            'name' => $user->name,
            'email' => 'attacker@example.com',
            'current_password' => 'wrong-6',
        ])->assertStatus(429);

        // Clamping the guesses never moved the address.
        $this->assertSame($originalEmail, $user->fresh()->email);
    }

    public function test_the_correct_password_still_moves_the_email_inside_the_budget(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 4; $i++) {
            $this->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => "wrong-{$i}",
            ])->assertSessionHasErrors('current_password');
        }

        // Four wasted guesses then the real one: a human retyping must not be
        // locked out of a legitimate self-service edit.
        $this->patch('/profile', [
            'name' => $user->name,
            'email' => 'new-owner@example.com',
            'current_password' => 'Correct-Horse-1!',
        ])->assertSessionHasNoErrors();

        $this->assertSame('new-owner@example.com', $user->fresh()->email);
    }

    public function test_the_account_deletion_oracle_locks_out_at_five_guesses_per_minute(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->delete('/profile', ['password' => "wrong-{$i}"])
                ->assertSessionHasErrorsIn('userDeletion', 'password');
        }

        $this->delete('/profile', ['password' => 'wrong-6'])->assertStatus(429);

        $this->assertNotNull($user->fresh());
        $this->assertTrue(Hash::check('Correct-Horse-1!', $user->fresh()->password));
    }

    public function test_the_correct_password_still_deletes_the_account_inside_the_budget(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 4; $i++) {
            $this->delete('/profile', ['password' => "wrong-{$i}"]);
        }

        $this->delete('/profile', ['password' => 'Correct-Horse-1!'])->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_the_two_profile_lanes_share_nothing_with_each_other_or_with_auth180(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 6; $i++) {
            $this->patch('/profile', [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => "wrong-{$i}",
            ]);
        }
        $this->patch('/profile', [
            'name' => $user->name,
            'email' => 'attacker@example.com',
            'current_password' => 'x',
        ])->assertStatus(429);

        // Exhausting the update oracle must not silence deletion, confirmation
        // or the password change — each is its own bucket.
        $this->delete('/profile', ['password' => 'wrong-once'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->post('/confirm-password', ['password' => 'wrong-once'])
            ->assertSessionHasErrors('password');
        $this->post('/profile/change-password', [
            'current_password' => 'wrong-once',
            'new_password' => 'Fresh-Pass-2!',
            'new_password_confirmation' => 'Fresh-Pass-2!',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_one_users_guessing_cannot_throttle_another_users_profile_edit(): void
    {
        $guesser = $this->user();
        $bystander = $this->user();

        $this->actingAs($guesser);
        for ($i = 1; $i <= 6; $i++) {
            $this->patch('/profile', [
                'name' => $guesser->name,
                'email' => 'spam@example.com',
                'current_password' => 'noise',
            ]);
        }
        $this->patch('/profile', [
            'name' => $guesser->name,
            'email' => 'spam@example.com',
            'current_password' => 'noise',
        ])->assertStatus(429);

        $this->actingAs($bystander);
        $this->patch('/profile', [
            'name' => 'Renamed',
            'email' => 'bystander@example.com',
            'current_password' => 'Correct-Horse-1!',
        ])->assertSessionHasNoErrors();
    }
}
