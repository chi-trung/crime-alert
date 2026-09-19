<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Issue #382: VerifyEmailController redirected to dashboard?verified=1 but
 * nothing in resources/views/ or public/js/ ever read that flag, so a user who
 * completed onboarding landed on a dashboard indistinguishable from the
 * unverified one. The messages.verify_email_success key had shipped with no
 * view wired to it.
 *
 * The controller also used to send ?verified=1 for BOTH branches — the fresh
 * verification and the stale already-verified link — so a user reopening an old
 * email link would be congratulated for something they did earlier.
 */
class VerifiedEmailFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user, bool $fresh): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        ).($fresh ? '' : '');
    }

    public function test_a_fresh_verification_shows_the_success_message(): void
    {
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => null,
        ])->save());

        $this->actingAs($user)
            ->get($this->verificationUrl($user, true))
            ->assertRedirect();

        // The redirect lands on the dashboard with the flag; follow it.
        $html = $this->actingAs($user->refresh())
            ->get(route('dashboard', ['verified' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('messages.verify_email_success'),
            $html,
            'a freshly verified user must see the confirmation on the dashboard'
        );
        $this->assertStringContainsString('alert-success', $html);
    }

    public function test_the_user_is_marked_verified_after_following_the_link(): void
    {
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => null,
        ])->save());

        $this->actingAs($user)->get($this->verificationUrl($user, true));

        $this->assertTrue(
            $user->refresh()->hasVerifiedEmail(),
            'following the signed link must mark the address verified'
        );
    }

    public function test_an_already_verified_user_gets_the_already_message(): void
    {
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        // The controller's first branch fires before markEmailAsVerified.
        $this->actingAs($user)
            ->get($this->verificationUrl($user, false))
            ->assertRedirect();

        $html = $this->actingAs($user)
            ->get(route('dashboard', ['already' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('messages.verify_email_already'),
            $html,
            'a user reopening a consumed link must be told nothing changed'
        );
        $this->assertStringNotContainsString(
            'alert-success',
            $html,
            'a stale link must not render the success alert'
        );
    }

    public function test_a_crafted_flag_does_not_congratulate_an_unverified_user(): void
    {
        // ?verified=1 is public state on a URL. Without the hasVerifiedEmail()
        // gate, a crafted flag would render a success notice the user did not
        // earn. The verified middleware keeps an unverified user off
        // /dashboard, but that is route protection, not blade protection —
        // this test bypasses the middleware to pin the gate where it lives.
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => null,
        ])->save());

        $html = $this->actingAs($user)
            ->withoutMiddleware(EnsureEmailIsVerified::class)
            ->get(route('dashboard', ['verified' => 1]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            __('messages.verify_email_success'),
            $html,
            'the flag must not render the success notice for an unverified user'
        );
    }

    public function test_the_dashboard_without_the_flag_is_unchanged(): void
    {
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            __('messages.verify_email_success'),
            $html,
            'the dashboard must not show the notice without the flag'
        );
        $this->assertStringNotContainsString(
            __('messages.verify_email_already'),
            $html
        );
    }
}
