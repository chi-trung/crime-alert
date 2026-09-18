<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #357: the user-profile menu anchor carried .profile-link but not
 * .dropdown-toggle, so the nav's generic click handler never bound it. The
 * menu opened only through CSS .user-profile:hover — which never fires on a
 * touch device — leaving "Hồ sơ cá nhân" and "Đăng xuất" unreachable on phones
 * and tablets: a hard lockout of sign-out on mobile. No :focus-within rule
 * existed either, so keyboard users were in the same position.
 *
 * The nav is inline in layouts/navigation.blade.php and renders for every
 * authenticated page.
 */
class ProfileDropdownTouchLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_link_is_a_dropdown_toggle(): void
    {
        // Without .dropdown-toggle the generic handler at navigation.blade.php
        // never binds, and the menu is hover-only.
        $html = $this->authenticatedNav();

        $this->assertStringContainsString(
            'class="profile-link dropdown-toggle"',
            $html,
            'the profile link must opt into the generic dropdown handler'
        );
    }

    public function test_profile_dropdown_opens_on_focus_within(): void
    {
        // :hover alone never fires on touch; keyboard users need focus.
        $css = $this->navStylesheet($this->authenticatedNav());

        $this->assertStringContainsString(
            '.user-profile:focus-within .profile-dropdown',
            $css,
            'the dropdown must open on focus-within, not only hover'
        );
        // The hover rule itself must survive — desktop still uses it.
        $this->assertStringContainsString(
            '.user-profile:hover .profile-dropdown',
            $css
        );
    }

    public function test_profile_dropdown_closes_on_outside_click(): void
    {
        // The menu is .profile-dropdown, not .dropdown-menu: the outside-click
        // closer used to skip it entirely, so once open it stayed open.
        $js = $this->navScript($this->authenticatedNav());

        $this->assertStringContainsString(
            "'.profile-dropdown'",
            $js,
            'the outside-click closer must also collapse the profile menu'
        );
    }

    public function test_both_profile_menu_destinations_still_render(): void
    {
        // Positive control: the fix must not cost the menu its two entries.
        $html = $this->authenticatedNav();

        $this->assertStringContainsString(route('profile.edit'), $html, 'the profile link must render');
        $this->assertStringContainsString(route('logout'), $html, 'the logout form must render');
        $this->assertStringContainsString('Đăng xuất', $html);
    }

    public function test_guest_nav_never_renders_the_profile_menu(): void
    {
        // Pins the trust boundary: the menu is authed-only, so a guest page
        // cannot carry the dropdown the assertions above read.
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="profile-link', $html);
        $this->assertStringNotContainsString('class="profile-dropdown', $html);
    }

    private function authenticatedNav(): string
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        return $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
    }

    /**
     * Pull the inline <style> blocks out of the page so a CSS assertion
     * searches real stylesheet text rather than the whole document.
     */
    private function navStylesheet(string $html): string
    {
        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }

    /**
     * Pull the inline <script> blocks (skipping external src includes) so a JS
     * assertion searches real script text rather than the page, which also
     * carries the #357 Blade comments naming the selectors under test.
     */
    private function navScript(string $html): string
    {
        preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }
}
