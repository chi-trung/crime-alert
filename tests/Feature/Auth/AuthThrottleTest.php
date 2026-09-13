<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Issue #33: register / forgot-password / reset-password POSTs were the
 * unthrottled auth endpoints (login carries its own LoginRequest limiter).
 * The route stack is guest -> throttle, so where a request authenticates
 * (registration), the test must return to guest state for the next hit to
 * reach the limiter.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_throttled_at_ten_requests_per_minute(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->post('/register', [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertRedirect(route('dashboard', absolute: false));

            // Store logs the new user in; the next attempt must be a guest
            // again or the guest middleware short-circuits before throttle.
            Auth::logout();
        }

        $this->post('/register', [
            'name' => 'User 11',
            'email' => 'user11@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_throttled_at_six_requests_per_minute(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/forgot-password', ['email' => "victim{$i}@example.com"])
                ->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'victim7@example.com'])
            ->assertStatus(429);
    }

    public function test_reset_password_is_throttled_at_six_requests_per_minute(): void
    {
        // A well-formed token that fails the broker check is still a routed
        // request; the limiter counts it. Only the 429 boundary matters here.
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
