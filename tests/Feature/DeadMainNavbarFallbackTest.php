<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #353: layouts/app.blade.php kept a manual #mainNavbar/.navbar-toggler
 * hamburger fallback and a matching !important style hack after the nav was
 * replaced by layouts/navigation.blade.php's own .modern-nav, which carries its
 * own button.mobile-toggle and its own handler. Neither id nor class was
 * rendered by any view, so the guard never passed and the rules matched
 * nothing. The test pins both the removal and the survival of the real toggle.
 */
class DeadMainNavbarFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_mainnavbar_markup_is_absent_from_every_rendered_page(): void
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        // An authenticated page renders the full layout, including the nav.
        // The dead code lived in the layout's own inline <script> as
        // getElementById('mainNavbar') / querySelector('.navbar-toggler'), so
        // that is what the assertion targets — not the rendered DOM, where
        // neither ever existed (which is exactly why the fallback was dead).
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $js = $this->inlineScripts($html);
        $this->assertStringNotContainsString("getElementById('mainNavbar')", $js, 'the dead nav lookup must be gone');
        $this->assertStringNotContainsString("querySelector('.navbar-toggler')", $js, 'the dead toggler lookup must be gone');

        // Positive control: the page must still ship a real inline script and
        // a real nav, otherwise the negatives above pass on an empty document.
        $this->assertNotEmpty($js, 'the layout must still render inline scripts');
        $this->assertStringContainsString('nav-container', $html, 'the live nav container must render');
    }

    public function test_dead_mainnavbar_css_hack_is_absent(): void
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        // The !important rule block targeted the dead id; only the chatbot
        // z-index rules survive it. Match the rule as a selector head.
        $styles = $this->inlineStyles($html);
        $this->assertDoesNotMatchRegularExpression(
            '/#mainNavbar\s*\{[^}]*!important/',
            $styles,
            'the dead #mainNavbar style hack must be gone'
        );
        $this->assertStringContainsString('.chatbot-header', $styles, 'the chatbot z-index rules must survive');
    }

    public function test_the_real_mobile_toggle_still_ships(): void
    {
        // The removal is only safe because the modern nav owns the toggle.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class="mobile-toggle"', $html, 'the live toggle button must render');
        $this->assertStringContainsString('nav-container', $html, 'the live nav container must render');

        // The handler that makes it work lives in the nav partial.
        $this->assertStringContainsString("querySelector('.mobile-toggle')", $html);
    }

    public function test_guest_pages_also_drop_the_dead_markup(): void
    {
        // The guest layout is a separate shell; assert it never grew a copy of
        // the dead lookup.
        $js = $this->inlineScripts($this->get('/login')->assertOk()->getContent());

        $this->assertStringNotContainsString("getElementById('mainNavbar')", $js);
        $this->assertStringNotContainsString("querySelector('.navbar-toggler')", $js);
    }

    /**
     * Collect every inline <script> body so a JS assertion searches real
     * script text rather than the whole page (which also carries Blade
     * comments naming the removed id while documenting the removal).
     */
    private function inlineScripts(string $html): string
    {
        preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }

    /**
     * Collect every inline <style> block so a CSS rule assertion searches real
     * stylesheet text rather than the whole page.
     */
    private function inlineStyles(string $html): string
    {
        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }
}
