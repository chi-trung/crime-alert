<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Issue #179: the #33 guest-route throttles were bare 'throttle:N,1' with no
 * lane prefix. ThrottleRequests keys a guest bucket by IP alone (no user id
 * to split by), so register / forgot-password / reset-password all hit ONE
 * shared per-IP counter while each route compared the shared hit count
 * against its own max. Consequence: a few signups from one NAT (office,
 * campus, carrier) 429'd everyone behind that IP out of password recovery —
 * silently defeating #33's documented per-endpoint intent. These tests pin
 * cross-route ISOLATION (each budget is independent) — the per-route budgets
 * alone could not see this bug, which is why the pre-fix AuthThrottleTest
 * suite stayed green. Same idiom as LikeThrottleTest's lane-isolation test
 * and CreateFanoutThrottleTest, but for the guest trio.
 */
class GuestAuthLaneIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * POST /register authenticates on success, and the guest middleware
     * short-circuits BEFORE the limiter — so every successful signup in a
     * burn-down loop must be followed by Auth::logout() (see
     * AuthThrottleTest's note) to keep the next request guest-visible.
     */
    private function registerUser(int $i): void
    {
        $this->post('/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        Auth::logout();
    }

    public function test_exhausting_the_register_lane_leaves_forgot_password_unaffected(): void
    {
        // Burn the 10/min register budget exactly.
        for ($i = 1; $i <= 10; $i++) {
            $this->registerUser($i);
        }
        $this->post('/register', [
            'name' => 'User 11',
            'email' => 'user11@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(429);

        // Pre-fix: register's 11 hits already exceed forgot-password's max of
        // 6 on the shared counter, so this 302 was a 429. Post-fix: its own
        // lane is untouched.
        $this->post('/forgot-password', ['email' => 'someone@example.com'])
            ->assertStatus(302);
    }

    public function test_exhausting_the_forgot_lane_leaves_reset_unaffected(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/forgot-password', ['email' => "victim{$i}@example.com"])
                ->assertStatus(302);
        }
        $this->post('/forgot-password', ['email' => 'victim7@example.com'])
            ->assertStatus(429);

        // A user completing a legitimately emailed reset must not be locked
        // out by reset-link email spam from the same IP. Pre-fix this shared
        // counter was already at 7 > 6 and returned 429.
        $payload = [
            'token' => 'not-a-real-token',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_exhausted_register_lane_leaves_reset_password_unaffected(): void
    {
        // The third isolation edge: after 10 signups the pre-fix shared IP
        // counter sits at 10 — already past reset-password's own cap of 6 —
        // so a user completing a legitimately emailed reset got 429 without
        // ever touching register. Note 429 responses do not increment the
        // limiter, so only the 10 successful signups load the shared bucket.
        for ($i = 1; $i <= 10; $i++) {
            $this->registerUser($i);
        }
        $this->post('/register', [
            'name' => 'User 11',
            'email' => 'user11@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(429);

        $payload = [
            'token' => 'not-a-real-token',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
    }

    public function test_each_guest_lane_keeps_its_own_documented_budget(): void
    {
        // If a lane name were ever typo-collided again, the budgets would
        // cross-contaminate; re-establishing each cap independently here
        // proves the three counters really are separate (forgot at its 6th
        // hit while register/reset are cold).
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/forgot-password', ['email' => "v{$i}@example.com"]);
        }
        $this->post('/forgot-password', ['email' => 'v7@example.com'])->assertStatus(429);

        // Same counter? no — reset still accepts all six of its own budget.
        $payload = [
            'token' => 'not-a-real-token',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        }
        $this->post('/reset-password', $payload)->assertStatus(429);
    }
}
