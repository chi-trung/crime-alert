<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Issue #335: three Breeze auth pages rendered fully in English while the
 * app runs locale vi (config/app.php:81). The blades called raw
 * __('Email Password Reset Link')-style keys with no vi entry, and
 * fallback_locale 'en' returns the SOURCE STRING verbatim — no exception,
 * no log, green CI — so the Vietnamese strings already sitting in
 * lang/vi/messages.php were never reached. /login and /register hardcode
 * their Vietnamese text, so the split lived inside the same directory.
 *
 * Fallback masking cuts both ways: it keeps a missing key from breaking the
 * page, but it also means ONLY a rendered-output assertion catches the
 * regression. These tests pin the Vietnamese strings on all three pages so
 * a future scaffold refresh cannot reintroduce English silently.
 */
class AuthLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders_vietnamese(): void
    {
        $html = $this->get('/forgot-password')->assertOk()->getContent();

        $this->assertStringContainsString('Gửi liên kết đặt lại mật khẩu', $html);
        // Angle brackets, not a bare substring: the blade's section comments
        // (<!-- Email Address -->) still name the English fields and would
        // trip a plain contains check.
        $this->assertStringNotContainsString('>Email Password Reset Link<', $html);
        $this->assertStringNotContainsString('Forgot your password', $html);
    }

    public function test_reset_password_page_renders_vietnamese(): void
    {
        // The token in the route is only echoed into the hidden input, so any
        // non-empty string reaches the form.
        $html = $this->get('/reset-password/probe-token')->assertOk()->getContent();

        // Pin the rendered label/button text (bare Vietnamese strings — the
        // label renders with surrounding whitespace, so no '>'/'<' anchors);
        // the negatives use the long English sentences that only appear as
        // user-visible text, never in a scaffold section comment.
        $this->assertStringContainsString('Đặt lại mật khẩu', $html);
        $this->assertStringContainsString('Xác nhận mật khẩu', $html);
        $this->assertStringNotContainsString('Email Password Reset Link', $html);
        $this->assertStringNotContainsString('Forgot your password', $html);
    }

    public function test_confirm_password_page_renders_vietnamese(): void
    {
        // password.confirm needs an authed user; the session timestamp is
        // written on submit, so GET just renders the form.
        $html = $this->actingAs(User::factory()->create())
            ->get('/confirm-password')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('khu vực bảo mật', $html);
        // Only long English sentences qualify as negatives here: the scaffold
        // section comments (<!-- Password -->) name the English fields.
        $this->assertStringNotContainsString('This is a secure area of the application', $html);
    }

    public function test_vietnamese_message_keys_used_by_the_auth_pages_resolve(): void
    {
        // A key that goes missing renders as its own name (the raw dot path),
        // which the page tests above would still pass on. Pin the keys
        // themselves so a typo in messages.php fails here, not in a browser.
        foreach ([
            'forgot_password_notice',
            'send_password_reset_link',
            'reset_password',
            'confirm_password',
            'confirm',
            'confirm_secure_area_notice',
            'email',
            'password',
        ] as $key) {
            $resolved = __("messages.{$key}");
            $this->assertNotSame(
                "messages.{$key}",
                $resolved,
                "The messages.{$key} translation key is missing from lang/vi."
            );
            $this->assertNotSame('', trim($resolved), "messages.{$key} is empty.");
        }
    }
}
