<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #168: the confirm-password field's oninput called
 * checkPasswordMatch() — a function that was never defined anywhere — so
 * every keystroke in #password_confirmation threw a ReferenceError and the
 * #passwordMatch indicator stayed empty forever. register.js now ships the
 * handler (comparing #password vs #password_confirmation and toggling the
 * same .valid/.invalid classes checkPasswordStrength already uses). These
 * tests pin both ends of the contract: the blade call site + target div,
 * and the JS definition with its exact Vietnamese strings.
 *
 * Note: the indicator is UI sugar only — the real gate stays the server-side
 * 'confirmed' rule in RegisteredUserController (already covered by
 * tests/Feature/Auth/RegistrationTest).
 */
class PasswordMatchHandlerTest extends TestCase
{
    private function registerJs(): string
    {
        $js = file_get_contents(public_path('js/register.js'));
        $this->assertNotFalse($js, 'public/js/register.js must exist and be readable.');

        return $js;
    }

    public function test_register_page_keeps_the_call_site_and_target_div(): void
    {
        $html = $this->get('/register')->getContent();

        // The oninput attribute the issue reports as dead — the handler name
        // must keep matching what register.js now defines.
        $this->assertStringContainsString('oninput="checkPasswordMatch()"', $html);
        $this->assertStringContainsString('id="passwordMatch"', $html);
    }

    public function test_register_js_defines_the_previously_missing_handler(): void
    {
        $js = $this->registerJs();

        $this->assertStringContainsString('function checkPasswordMatch()', $js);
        // It must read the same three elements the blade ships.
        $this->assertStringContainsString("getElementById('password')", $js);
        $this->assertStringContainsString("getElementById('password_confirmation')", $js);
        $this->assertStringContainsString("getElementById('passwordMatch')", $js);
    }

    public function test_handler_ships_the_exact_vietnamese_indicator_strings(): void
    {
        $js = $this->registerJs();

        // The blade test asserts the div exists; the page is Vietnamese, so
        // these literals are part of the user-facing contract.
        $this->assertStringContainsString("'Mật khẩu khớp'", $js);
        $this->assertStringContainsString("'Mật khẩu không khớp'", $js);
        // Matching the strength idiom: reuse .valid/.invalid (register.css
        // colors them #10b981 / #ef4444).
        $this->assertStringContainsString("classList.add('valid')", $js);
        $this->assertStringContainsString("classList.add('invalid')", $js);
    }

    public function test_handler_clears_indicator_when_confirmation_is_blank(): void
    {
        $js = $this->registerJs();

        // Empty confirm field must blank the indicator, not flash "not
        // matched" while the user is still typing the first characters.
        $this->assertMatchesRegularExpression(
            "/confirmation\.value === ''\)\s*\{\s*match\.textContent = ''/",
            $js,
            'blank confirmation must clear #passwordMatch before any match verdict is shown'
        );
    }

    public function test_handler_compares_both_field_values_not_the_raw_argument(): void
    {
        $js = $this->registerJs();

        // The call site passes no argument (checkPasswordMatch()), so the
        // body must read password.value itself — a stale this.value would
        // silently compare against undefined.
        $this->assertMatchesRegularExpression(
            '/password\.value === confirmation\.value/',
            $js,
            'match verdict must compare #password against #password_confirmation'
        );
    }
}
