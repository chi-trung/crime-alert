<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    /**
     * Issue #123: the reset door is the only one available to a victim who is
     * locked out of their account, so it has to carry #27's rotation guarantee.
     * These tests force SESSION_DRIVER=database so the hijacker's server-side
     * session row exists to be swept; phpunit.xml runs the suite on the array
     * driver otherwise.
     */
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

    public function test_reset_invalidates_other_sessions_of_that_user_only(): void
    {
        config(['session.driver' => 'database']);
        Notification::fake();

        $user = User::factory()->create();
        $bystander = User::factory()->create();
        $this->insertSession('attacker-session-1', $user->id);
        $this->insertSession('attacker-session-2', $user->id);
        $this->insertSession('bystander-session', $bystander->id);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'rotated-secret-password',
                'password_confirmation' => 'rotated-secret-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        // The hijacker's live sessions are gone...
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session-2']);
        // ...without collateral damage to another user's session.
        $this->assertDatabaseHas('sessions', ['id' => 'bystander-session']);
    }

    public function test_failed_reset_leaves_sessions_untouched(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->insertSession('still-valid-session', $user->id);

        // A bad token never reaches the callback, so the sweep must not run:
        // an attacker probing reset links cannot use the form to log the real
        // user out.
        $this->post('/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'rotated-secret-password',
            'password_confirmation' => 'rotated-secret-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('sessions', ['id' => 'still-valid-session']);
    }
}
