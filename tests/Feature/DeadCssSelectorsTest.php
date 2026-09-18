<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #363 + #370: CSS selectors that styled classes no markup and no JS
 * ever carried. Cross-checked top-level class selectors across the stylesheets
 * against every class="…" in resources/views/ and every '.foo' string in
 * public/js/, then hand-probed the borderline cases (three classes are added
 * dynamically by JS and so looked dead to a markup-only scan: .particle,
 * .valid/.invalid, .photo-missing).
 *
 * Dead CSS is unfalsifiable from a browser screenshot alone, so the tests
 * pin the token in the served stylesheet.
 */
class DeadCssSelectorsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The stylesheets are static assets, so they are read from disk rather
     * than through a page render: three of the pages carrying them sit
     * behind auth+verified middleware, and the CSS itself does not depend
     * on which page pulls it in.
     */
    private function stylesheet(string $asset): string
    {
        $css = file_get_contents(public_path($asset));
        $this->assertNotEmpty($css, "{$asset} must exist and be non-empty");

        return $css;
    }

    public function test_alerts_create_has_no_danger_gradient_rule(): void
    {
        $this->assertSame(
            0,
            substr_count($this->stylesheet('css/alerts_create.css'), '.bg-danger-gradient'),
            'the unused .bg-danger-gradient rule must be gone'
        );
    }

    public function test_dashboard_has_no_zoom_on_hover_rule(): void
    {
        $this->assertSame(
            0,
            substr_count($this->stylesheet('css/dashboard.css'), '.zoom-on-hover'),
            'the unused .zoom-on-hover rule must be gone'
        );
    }

    public function test_login_has_no_login_card_rule(): void
    {
        $css = $this->stylesheet('css/login.css');

        $this->assertSame(0, substr_count($css, '.login-card'), 'the unused .login-card rules must be gone');

        // The live selectors the file also styles must survive the removal.
        $this->assertStringContainsString('.input-group {', $css);
        $this->assertStringContainsString('.input-icon {', $css);
        $this->assertStringContainsString('.form-input {', $css);
        $this->assertStringContainsString('.btn-login {', $css);
    }

    public function test_register_has_no_register_card_rule(): void
    {
        $css = $this->stylesheet('css/register.css');

        $this->assertSame(0, substr_count($css, '.register-card'), 'the unused .register-card rules must be gone');

        // Positive control: the strength-meter colours register.js toggles.
        $this->assertStringContainsString('.valid {', $css);
        $this->assertStringContainsString('.invalid {', $css);
    }

    public function test_support_show_has_no_chat_avatar_rule(): void
    {
        $css = $this->stylesheet('css/support_show.css');

        $this->assertSame(
            0,
            substr_count($css, '.support-chat-avatar'),
            'the unused .support-chat-avatar rules must be gone'
        );

        // Positive control: the markup and the JS bubble builder both use these.
        $this->assertStringContainsString('.support-chat-container {', $css);
        $this->assertStringContainsString('.support-chat-bubble {', $css);
    }

    public function test_dynamically_added_classes_still_have_rules(): void
    {
        // Guards the false-positive class of this sweep: classes a markup-only
        // scan reports as dead because JS adds them at runtime.
        $this->assertStringContainsString(
            '.particle',
            $this->stylesheet('css/welcome.css'),
            'createParticles() sets className to "particle" on every dot it builds'
        );
        $this->assertStringContainsString(
            '.photo-missing',
            $this->stylesheet('css/dashboard.css'),
            'the wanted-list img adds .photo-missing in its onerror handler'
        );
    }

    public function test_dashboard_has_no_avatar_chrome(): void
    {
        // #370: .avatar-sm/.avatar-title render on one page only,
        // alerts/admin_index.blade.php, which loads a different stylesheet.
        $css = $this->stylesheet('css/dashboard.css');

        $this->assertSame(0, substr_count($css, '.avatar-sm'), 'the unused avatar box must be gone');
        $this->assertSame(0, substr_count($css, '.avatar-title'), 'the unused avatar disc must be gone');

        // Positive controls the same file must keep: dashboard.blade.php:211
        // does render a <table>, and the wanted-list img onerror still fires.
        $this->assertStringContainsString('.table th {', $css);
        $this->assertStringContainsString('.bg-gradient-primary {', $css);
    }

    public function test_experiences_index_has_no_table_rules(): void
    {
        // #370: the only page loading this sheet renders no <table> and no
        // .card-header — it is a card list.
        $css = $this->stylesheet('css/experiences_index.css');

        $this->assertSame(0, substr_count($css, '.table-hover'), 'the unused hover rule must be gone');
        $this->assertSame(0, substr_count($css, '.card-header'), 'the unused card header must be gone');

        // Positive controls: markup on that page carries all four.
        $this->assertStringContainsString('.card {', $css);
        $this->assertStringContainsString('.badge {', $css);
        $this->assertStringContainsString('.btn-sm {', $css);
        $this->assertStringContainsString('.text-muted {', $css);
    }

    public function test_register_has_no_unreachable_password_bar_rules(): void
    {
        // #370: .password-toggle lived only inside HTML comments; .password-
        // strength styled a div checkPasswordStrength() never populated.
        $css = $this->stylesheet('css/register.css');

        $this->assertSame(0, substr_count($css, '.password-toggle'), 'the commented-out toggle rules must be gone');
        $this->assertSame(0, substr_count($css, '.password-strength'), 'the unreachable strength bar must be gone');

        // Positive controls: the strength indicator that does get populated.
        $this->assertStringContainsString('.password-requirements {', $css);
        $this->assertStringContainsString('.requirement {', $css);
        $this->assertStringContainsString('.valid {', $css);
        $this->assertStringContainsString('.invalid {', $css);
        // register.blade.php:36 carries this on the graphic panel.
        $this->assertStringContainsString('.animate-bounce-slow {', $css);
    }

    public function test_register_markup_drops_the_unreachable_strength_bar(): void
    {
        // The div was the other half of the same defect: it rendered, took
        // space, and stayed blank on every keystroke because no code path
        // ever wrote it.
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'id="passwordStrength"',
            $html,
            'the unreachable strength bar div must be gone'
        );

        // Positive controls: the input still drives the live indicator.
        $this->assertStringContainsString('oninput="checkPasswordStrength(this.value)"', $html);
        $this->assertStringContainsString('id="passwordRequirements"', $html);
        $this->assertStringContainsString('id="lengthReq"', $html);
    }
}
