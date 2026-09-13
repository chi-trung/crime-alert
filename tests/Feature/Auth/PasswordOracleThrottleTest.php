<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #180: /confirm-password and /profile/change-password were the two
 * live password-verification oracles with no limiter at all — a correct
 * guess redirects to the intended page, a wrong one bounces back with an
 * error bag, and an attacker holding any session (shared workstation; XSS
 * shipped twice, #18/#77) could grind the victim's plaintext password at
 * network speed, immune to login's deliberate 5-attempt cap
 * (LoginRequest::ensureIsNotRateLimited). Recovering the plaintext defeats
 * the #27/#63/#123 rotation hardening (the attacker just re-logs in) and
 * feeds cross-site credential reuse. Both POSTs now carry 5/min (mirroring
 * login's budget) in dedicated #147 lanes ('auth-pw-confirm',
 * 'auth-pw-change') so they never share a counter with each other, the
 * #179 guest lanes, or the authed content lanes.
 */
class PasswordOracleThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['password' => 'Correct-Horse-1!']);
    }

    public function test_confirm_password_locks_out_at_five_attempts_per_minute(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/confirm-password', ['password' => "wrong-{$i}"])
                ->assertSessionHasErrors('password');
        }

        // Pre-fix: this 6th guess answered just like the first five — the
        // oracle was unlimited. Now the lane clamps it.
        $this->post('/confirm-password', ['password' => 'wrong-6'])
            ->assertStatus(429);
    }

    public function test_correct_password_still_confirms_inside_the_budget(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        // Four wasted guesses, then the real one — a human fumbling their
        // keys must not be locked out at 5/min.
        for ($i = 1; $i <= 4; $i++) {
            $this->post('/confirm-password', ['password' => "wrong-{$i}"])
                ->assertSessionHasErrors('password');
        }
        $this->post('/confirm-password', ['password' => 'Correct-Horse-1!'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_change_password_locks_out_at_five_attempts_per_minute(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $payload = fn (string $current) => [
            'current_password' => $current,
            'new_password' => 'Fresh-Pass-2!',
            'new_password_confirmation' => 'Fresh-Pass-2!',
        ];

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/profile/change-password', $payload("wrong-{$i}"))
                ->assertSessionHasErrors('current_password');
        }

        $this->post('/profile/change-password', $payload('wrong-6'))
            ->assertStatus(429);

        // None of the 429'd/failed guesses touched the stored hash.
        $this->assertTrue(Hash::check('Correct-Horse-1!', $user->fresh()->password));
    }

    public function test_the_two_oracle_lanes_do_not_share_a_counter(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/confirm-password', ['password' => "wrong-{$i}"]);
        }
        $this->post('/confirm-password', ['password' => 'wrong-6'])->assertStatus(429);

        // Exhausting the confirm oracle must not silence the change oracle —
        // e.g. a legitimate password change right after failed confirms.
        // Pre-#147-style bare throttles would have shared one per-user
        // bucket across both (and every other limiter in the app).
        $this->post('/profile/change-password', [
            'current_password' => 'Correct-Horse-1!',
            'new_password' => 'Fresh-Pass-2!',
            'new_password_confirmation' => 'Fresh-Pass-2!',
        ])->assertRedirect();
    }

    public function test_lanes_are_per_user_not_shared_through_a_victims_session_guesser(): void
    {
        $guesser = $this->user();
        $bystander = $this->user();

        // One user's spam cannot throttle another user's routine confirm.
        // (ThrottleRequests keys authed requests by user id + lane prefix.)
        $this->actingAs($guesser);
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/confirm-password', ['password' => 'noise']);
        }
        $this->post('/confirm-password', ['password' => 'noise'])->assertStatus(429);

        $this->actingAs($bystander);
        $this->post('/confirm-password', ['password' => 'Correct-Horse-1!'])
            ->assertRedirect(route('dashboard', absolute: false));
    }
}
