<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #380: verify-email.blade.php rendered four Vietnamese strings inline
 * while lang/vi/messages.php already carried the verify_email* family — the
 * same #335 pattern (auth page rendering raw strings instead of keys), except
 * the raw strings happened to be correct Vietnamese, so the page was never
 * visibly wrong. The cost is the orphaned glossary: no other locale can
 * render this page.
 *
 * The notice value also did not match the page: it read "before you continue,
 * check your email", inherited from Breeze's English string, while the page
 * actually says "thanks for registering, check your email, resend below".
 */
class VerifyEmailI18nTest extends TestCase
{
    use RefreshDatabase;

    private function renderVerifyEmail(): string
    {
        // An unverified user is exactly who this page is for — the verified
        // middleware redirects anyone else to the dashboard.
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => null,
        ])->save());

        return $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_renders_the_heading_key(): void
    {
        $html = $this->renderVerifyEmail();

        $this->assertStringContainsString(
            __('messages.verify_email'),
            $html,
            'the heading must come from the messages key, not an inline string'
        );
    }

    public function test_the_page_renders_the_notice_key(): void
    {
        $html = $this->renderVerifyEmail();

        // The <br> tags are part of the key and ship via {!! !!}.
        $this->assertStringContainsString(
            __('messages.verify_email_notice'),
            $html,
            'the page body must come from the messages key'
        );
    }

    public function test_the_page_renders_the_resend_button_key(): void
    {
        $html = $this->renderVerifyEmail();

        $this->assertStringContainsString(
            __('messages.verify_email_resend'),
            $html,
            'the resend button label must come from the messages key'
        );
    }

    public function test_the_page_renders_the_logout_key(): void
    {
        $html = $this->renderVerifyEmail();

        $this->assertStringContainsString(
            __('messages.logout'),
            $html,
            'the logout button label must come from the messages key'
        );
    }

    public function test_the_sent_confirmation_renders_the_key(): void
    {
        // session('status') === 'verification-link-sent' shows the alert.
        $user = tap(User::factory()->unverified()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => null,
        ])->save());

        $html = $this->actingAs($user)
            ->withSession(['status' => 'verification-link-sent'])
            ->get(route('verification.notice'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            __('messages.verify_email_sent'),
            $html,
            'the "link sent" confirmation must come from the messages key'
        );
    }

    public function test_the_verify_email_family_is_defined(): void
    {
        // Positive control on the lang file itself: every key the page pulls
        // must exist, or the fallback locale silently serves English.
        foreach ([
            'verify_email',
            'verify_email_notice',
            'verify_email_sent',
            'verify_email_resend',
            'verify_email_success',
        ] as $key) {
            $this->assertNotNull(
                __("messages.{$key}"),
                "messages.{$key} must exist"
            );
            // A key that resolves to its own dotted name is the signature of a
            // missing entry being returned raw.
            $this->assertNotSame(
                "messages.{$key}",
                __("messages.{$key}"),
                "messages.{$key} must resolve to a translation, not the key itself"
            );
        }
    }
}
