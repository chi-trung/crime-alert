<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Forgot-password answered "sent" for a registered email and flashed
 * passwords.user ("Không tìm thấy người dùng với địa chỉ email này.") in the
 * email error bag for an unregistered one — a guest-reachable oracle over
 * which accounts exist. #180/#147 capped the grind RATE (throttle:6,1) but
 * the distinct branch was the defect; six probes per minute still enumerates
 * at ~8,600 addresses a day. OWASP's reset-flow guidance (and this repo's own
 * login doctrine — auth.failed is uniform for unknown email and wrong
 * password) is one uniform answer.
 */
class PasswordResetEnumerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unregistered_email_gets_the_same_success_flash_as_a_registered_one(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'member@example.com']);

        // Pre-fix: this flashed errors.email = passwords.user — the oracle.
        $this->post('/forgot-password', ['email' => 'ghost@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));

        $this->post('/forgot-password', ['email' => 'member@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_collapse_does_not_silently_break_the_real_reset_send(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_two_answers_carry_identical_flash_state(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'member@example.com']);

        // Pre-fix the unregistered answer flashed an error bag plus the
        // old-input repopulation; the registered one flashed only 'status'.
        // Identical observable flash is what "no oracle" has to mean.
        $probe = function (string $email): array {
            $this->post('/forgot-password', ['email' => $email]);
            $session = $this->app['session'];

            return [
                'status' => $session->get('status'),
                'errors' => $session->has('errors')
                    ? $session->get('errors')->getBags()
                    : null,
                'old_input' => $session->get('_old_input'),
            ];
        };

        $this->assertSame($probe('ghost@example.com'), $probe('member@example.com'));
    }
}
