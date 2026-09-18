<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #365: two defects in the inline nav script of
 * layouts/navigation.blade.php, which renders for every authenticated page.
 *
 * 1. The click toggle and the outside-click closer wrote
 *    menu.style.display = 'none' inline. Desktop CSS opens these menus
 *    through opacity/visibility and never touches display, so after one
 *    JS close the inline rule outlived the click and :hover could not open
 *    the menu again until the page was reloaded. This was a regression
 *    introduced by #357, which wired the profile menu into that same
 *    closing machinery. Fixed by toggling a .menu-open class on the
 *    container instead, which the CSS pairs with :hover at both breakpoints.
 * 2. The notification poll had no .catch() and read data.notifications.length
 *    unguarded. A 401 (expired session) answers {success:false, message,
 *    redirect} with no count/notifications keys, so the read threw a
 *    TypeError and the 10s interval re-threw for as long as the tab was open.
 */
class NavHoverAndPollCrashTest extends TestCase
{
    use RefreshDatabase;

    private function navScript(): string
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $m);
        $joined = implode("\n", $m[1] ?? []);
        $this->assertNotEmpty($joined, 'the nav must ship inline script for an authed user');

        return $joined;
    }

    private function navStylesheet(): string
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }

    public function test_the_toggle_no_longer_writes_inline_display(): void
    {
        $js = $this->navScript();

        // The old shape assigned inline display on the *menu* element. The
        // badge still flips its own display — that is a different element and
        // is correct — so the negative is scoped to the menu containers.
        $withoutComments = preg_replace('/^[ \t]*\/\/.*$/m', '', $js);

        $this->assertStringNotContainsString(
            'menu.style.display',
            $withoutComments,
            'the toggle must open menus through a class, not inline display'
        );
        $this->assertStringNotContainsString(
            'm.style.display',
            $withoutComments,
            'the close-others sweep must not write inline display'
        );
        $this->assertStringContainsString(
            "classList.add('menu-open')",
            $js,
            'the toggle must add the menu-open class'
        );
    }

    public function test_the_outside_click_closer_uses_the_class(): void
    {
        $js = $this->navScript();

        $withoutComments = preg_replace('/^[ \t]*\/\/.*$/m', '', $js);

        $this->assertStringNotContainsString(
            'menu.style.display',
            $withoutComments,
            'the outside-click closer must not write inline display on the menu'
        );
        $this->assertStringContainsString(
            "classList.remove('menu-open')",
            $js,
            'the outside-click closer must remove the menu-open class'
        );
    }

    public function test_css_pairs_menu_open_with_hover_at_both_breakpoints(): void
    {
        // Without the .menu-open alternative in CSS, a class toggle has no
        // style to open the menu with.
        $css = $this->navStylesheet();

        $this->assertStringContainsString('.nav-item.menu-open .dropdown-menu', $css);
        $this->assertStringContainsString('.user-profile.menu-open .profile-dropdown', $css);
        // Hover must remain the desktop mechanism it always was.
        $this->assertStringContainsString('.nav-item:hover .dropdown-menu', $css);
        $this->assertStringContainsString('.user-profile:hover .profile-dropdown', $css);
    }

    public function test_the_poll_guards_against_a_body_without_notifications(): void
    {
        $js = $this->navScript();

        $this->assertStringContainsString(
            'Array.isArray(data.notifications)',
            $js,
            'the poll must bail out before reading data.notifications when the key is absent'
        );
        $this->assertStringContainsString(
            '.catch(() => {})',
            $js,
            'the poll must not surface a rejected promise to the browser console'
        );
    }

    public function test_the_unread_endpoint_answers_keys_the_poll_expects(): void
    {
        // Positive control: the guard must not start rejecting healthy
        // responses, so pin what the endpoint actually returns.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $this->actingAs($user)
            ->getJson(route('notifications.unread'))
            ->assertOk()
            ->assertJsonStructure(['count', 'notifications']);
    }

    public function test_an_expired_session_answers_no_notifications_key(): void
    {
        // Pins why the guard above is needed: an unauthenticated JSON request
        // gets a body that would otherwise throw.
        $this->getJson(route('notifications.unread'))
            ->assertStatus(401)
            ->assertJsonMissing(['notifications']);
    }

    public function test_nav_markup_and_toggle_wiring_survive(): void
    {
        // Positive control: the refactor must keep the toggle class the
        // handler binds to, and the menus it targets.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class="nav-link dropdown-toggle"', $html);
        $this->assertStringContainsString('class="profile-link dropdown-toggle"', $html);
        $this->assertStringContainsString('class="dropdown-menu', $html);
        $this->assertStringContainsString('class="profile-dropdown', $html);
        $this->assertStringContainsString('setInterval(fetchNotifications, 10000)', $html);
    }
}
