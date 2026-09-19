<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #406: the two nav action menus open correctly for mouse and keyboard
 * (#374, #400 — the toggles carry aria-expanded, aria-haspopup and
 * aria-controls), but the containers themselves were plain <div>s.
 *
 * aria-haspopup="true" announces "this opens a menu" to a screen reader while
 * the controlled element is a generic region, so the links inside lost their
 * menu context and role="menuitem" had nothing to hang on. That is a WCAG
 * 1.3.1 Info and Relationships failure — the UI structure exists but is not
 * expressed programmatically.
 *
 * The notification dropdown is deliberately out of scope: it is a feed, not an
 * action menu, and #400 already gave its list role="list" (role="list" is not
 * a valid child of role="menu", so forcing it would have broken #400).
 */
class NavMenuRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_category_menu_declares_its_role(): void
    {
        $html = $this->renderNav();

        $this->assertSame(
            1,
            preg_match('/<div[^>]*id="category-dropdown"[^>]*>/i', $html, $m),
            'the category menu container must render exactly once'
        );
        $this->assertStringContainsString('role="menu"', $m[0], 'the toggle announces aria-haspopup, the container must honour it');
    }

    public function test_the_profile_menu_declares_its_role(): void
    {
        $html = $this->renderNav();

        $this->assertSame(
            1,
            preg_match('/<div[^>]*id="profile-dropdown"[^>]*>/i', $html, $m),
            'the profile menu container must render exactly once'
        );
        $this->assertStringContainsString('role="menu"', $m[0], 'the toggle announces aria-haspopup, the container must honour it');
    }

    public function test_the_category_menu_items_are_menuitems(): void
    {
        $html = $this->renderNav();

        // The container is the anchor: assert the items inside it carry the
        // role, so the relationship announced by the toggle is real at every
        // level. Exactly four items — this also pins that no item was lost.
        preg_match('/<div[^>]*id="category-dropdown"[^>]*>(.*?)<\/div>\s*<\/li>/s', $html, $m);
        $this->assertNotEmpty($m, 'the category menu block must render');
        $this->assertSame(
            4,
            preg_match_all('/<a[^>]*class="[^"]*dropdown-item[^"]*"[^>]*role="menuitem"[^>]*>/i', $m[1]),
            'all four category menu items must carry role="menuitem"'
        );
    }

    public function test_the_profile_menu_items_are_menuitems_including_logout(): void
    {
        $html = $this->renderNav();

        preg_match('/<div[^>]*id="profile-dropdown"[^>]*>(.*?)<\/div>/s', $html, $m);
        $this->assertNotEmpty($m, 'the profile menu block must render');

        // The profile link is an anchor; logout is a POST form, so the role
        // sits on the form rather than on a button. Both must be menuitems or
        // the menu announces a half-realised structure.
        $this->assertSame(
            2,
            preg_match_all('/<(?:a|form)[^>]*class="[^"]*dropdown-item[^"]*"[^>]*role="menuitem"[^>]*>/i', $m[1]),
            'the profile link and the logout form must both carry role="menuitem"'
        );
        $this->assertStringContainsString('role="menuitem"', $m[1], 'the logout item must keep its role while staying a POST form');
    }

    public function test_the_notification_dropdown_is_not_recast_as_a_menu(): void
    {
        $html = $this->renderNav();

        $this->assertSame(
            1,
            preg_match('/<div[^>]*class="notification-dropdown"[^>]*>/i', $html, $m),
            'the notification dropdown container must render exactly once'
        );
        $this->assertStringNotContainsString(
            'role="menu"',
            $m[0],
            'the notification panel is a feed, not an action menu — role="list" from #400 is not a valid child of role="menu"'
        );
    }

    private function renderNav(): string
    {
        $user = User::factory()->create();

        return $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
    }
}
