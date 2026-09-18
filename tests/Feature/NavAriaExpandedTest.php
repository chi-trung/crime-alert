<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #374: the nav toggles menus by flipping classes but never reported the
 * open state. aria-expanded appeared nowhere in the project, so a screen reader
 * could not tell a collapsed menu from an open one. Three toggles, all in
 * layouts/navigation.blade.php, which renders for every authenticated page:
 * the mobile hamburger (.nav-container.active) and the two dropdown anchors
 * (.menu-open). Also: the category dropdown anchor carried
 * href="javascript:void(0)" — a link that goes nowhere is the wrong element
 * for a control that only toggles content.
 */
class NavAriaExpandedTest extends TestCase
{
    use RefreshDatabase;

    private function navHtml(): string
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        return $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    }

    private function navScript(string $html): string
    {
        preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $m);

        $joined = implode("\n", $m[1] ?? []);
        $this->assertNotEmpty($joined, 'the nav must ship inline script for an authed user');

        return $joined;
    }

    public function test_the_hamburger_reports_its_state(): void
    {
        $html = $this->navHtml();

        $this->assertStringContainsString(
            'aria-expanded="false" aria-controls="nav-main"',
            $html,
            'the hamburger must declare its collapsed state and the region it controls'
        );
        $this->assertStringContainsString('id="nav-main"', $html);
    }

    public function test_the_hamburger_flips_aria_expanded_and_label(): void
    {
        $js = $this->navScript($this->navHtml());

        // The class flip and the ARIA write must stay together — the whole
        // point is that the announcement tracks what the CSS acts on.
        $this->assertStringContainsString("classList.toggle('active')", $js);
        $this->assertStringContainsString(
            "mobileToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false')",
            $js,
            'the hamburger must report the state the CSS already acts on'
        );
        $this->assertStringContainsString(
            "mobileToggle.setAttribute('aria-label', isOpen ? 'Đóng menu' : 'Mở menu')",
            $js,
            'the label must switch with the state, in the page language'
        );
    }

    public function test_the_dropdown_toggles_declare_their_state(): void
    {
        $html = $this->navHtml();

        $this->assertStringContainsString(
            'class="nav-link dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="category-dropdown"',
            $html,
            'the category toggle must declare its state and the menu it controls'
        );
        $this->assertStringContainsString('id="category-dropdown"', $html);
        $this->assertStringContainsString(
            'class="profile-link dropdown-toggle" aria-expanded="false" aria-haspopup="true" aria-controls="profile-dropdown"',
            $html,
            'the profile toggle must declare its state and the menu it controls'
        );
        $this->assertStringContainsString('id="profile-dropdown"', $html);
    }

    public function test_the_toggle_handler_keeps_aria_in_step(): void
    {
        $js = $this->navScript($this->navHtml());

        $this->assertStringContainsString(
            'function syncDropdownToggles()',
            $js,
            'there must be a sync the toggling paths call'
        );
        // Both paths that flip .menu-open must re-sync: the click toggle and
        // the outside-click closer.
        $this->assertStringContainsString(
            "toggle.setAttribute('aria-expanded', 'true')",
            $js
        );
        $this->assertStringContainsString(
            "toggle.setAttribute('aria-expanded', 'false')",
            $js
        );
        $this->assertSame(
            2,
            substr_count($js, 'syncDropdownToggles();'),
            'the toggle and the outside-click closer must both re-sync'
        );
    }

    public function test_the_category_toggle_is_no_longer_a_dead_link(): void
    {
        $html = $this->navHtml();

        // The old anchor went nowhere; a button is the accessible shape for a
        // control that only toggles content.
        $this->assertStringNotContainsString(
            'href="javascript:void(0)"',
            $html,
            'the void href must be gone from the nav'
        );
        $this->assertStringContainsString(
            '<button type="button" class="nav-link dropdown-toggle"',
            $html
        );
    }

    public function test_the_button_toggle_is_styled_like_its_siblings(): void
    {
        // Without the reset a <button> ships with browser border/background and
        // looks like a form control dropped into the nav.
        $css = $this->navStylesheet();

        $this->assertStringContainsString('button.nav-link {', $css);
        $this->assertStringContainsString('border: none;', $css);
        $this->assertStringContainsString('background: none;', $css);
    }

    public function test_the_existing_toggle_behaviour_survives(): void
    {
        // Positive control: #365's class-based mechanism is what the ARIA sync
        // rides on, so it must be intact.
        $js = $this->navScript($this->navHtml());

        $this->assertStringContainsString("classList.add('menu-open')", $js);
        $this->assertStringContainsString("classList.remove('menu-open')", $js);
        $this->assertStringContainsString('.nav-item.menu-open', $js);
        $this->assertStringContainsString('.user-profile.menu-open', $js);
    }

    private function navStylesheet(): string
    {
        $html = $this->navHtml();
        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $m);

        $css = implode("\n", $m[1] ?? []);
        $this->assertNotEmpty($css, 'the nav must ship inline styles');

        return $css;
    }
}
