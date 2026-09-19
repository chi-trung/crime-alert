<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #400: two panels in the chrome were unusable for keyboard and screen
 * reader users.
 *
 * The notification bell (navigation.blade.php) was a bare SVG plus a number —
 * its accessible name was empty — and it announced no expanded state. Worse,
 * its dropdown opened on CSS :hover alone, so a touch device or keyboard could
 * never reach it (the same lockout #357 fixed for the profile menu). The badge
 * it carries is rewritten every 10s by the unread-notifications poll but was
 * not a live region, so a new notification arrived in silence.
 *
 * The chatbot widget (app.blade.php) was a hidden/shown container with no
 * dialog semantics, no Escape handler, an input focused 300ms after open, and
 * no focus returned to the trigger on close — the same behavioural gap #398
 * closed for the share popups.
 *
 * These tests pin the markup contract the behaviours depend on, and that the
 * dynamic DOM the poll writes keeps the list semantics a screen reader needs.
 * They assert on the element, never on the whole document (round-61 doctrine:
 * a page-level substring assertion passes on the pre-fix tree because the
 * layout already ships attributes for other widgets).
 */
class NavigationA11yTest extends TestCase
{
    use RefreshDatabase;

    private function navHtml(): string
    {
        $user = User::factory()->create();

        return $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();
    }

    public function test_the_notification_bell_is_named_and_announces_its_state(): void
    {
        $html = $this->navHtml();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*id="notification-toggle"[^>]*>/i',
            $html,
            'the notification bell must render'
        );
        preg_match('/<a[^>]*id="notification-toggle"[^>]*>/i', $html, $m);
        $bell = $m[0];

        $this->assertStringContainsString('aria-label="Thông báo"', $bell, 'the bell needs an accessible name — a bare SVG glyph has none');
        $this->assertStringContainsString('aria-expanded="false"', $bell, 'the bell must announce the dropdown state');
        $this->assertStringContainsString('aria-haspopup="true"', $bell, 'the bell must announce it opens a popup');
    }

    public function test_the_bell_svg_is_hidden_and_the_badge_is_a_live_region(): void
    {
        $html = $this->navHtml();

        // The bell glyph is decorative; the aria-label carries the name, so the
        // svg must not be announced as a second empty image node.
        preg_match('/<a[^>]*id="notification-toggle"[^>]*>(.*?)<\/a>/s', $html, $m);
        $this->assertStringContainsString('aria-hidden="true"', $m[1], 'the bell glyph is decoration on a labelled link');

        // The poll rewrites this span's text every 10s; without a live region a
        // new notification arrives and no screen reader user is told.
        $this->assertMatchesRegularExpression(
            '/<span[^>]*class="notification-badge"[^>]*>/i',
            $html,
            'the badge must render'
        );
        preg_match('/<span[^>]*class="notification-badge"[^>]*>/i', $html, $b);
        $this->assertStringContainsString('aria-live="polite"', $b[0], 'the badge count must be announced when the poll changes it');
        $this->assertStringContainsString('role="status"', $b[0], 'the badge is a status region');
    }

    public function test_the_notification_list_ships_list_semantics(): void
    {
        $user = User::factory()->create();
        // A user with zero notifications renders the @forelse empty branch, so
        // seed one real row to exercise the populated branch too.
        $user->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\SomeFutureClass',
            'data' => ['message' => 'Một thông báo'],
            'read_at' => null,
        ]);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="notification-list" role="list"', $html, 'the list needs role=list so screen readers treat rows as a list');
        $this->assertStringContainsString('class="notification-item" role="listitem"', $html, 'a seeded row must ship role=listitem');
    }

    public function test_the_empty_notification_list_still_ships_list_semantics(): void
    {
        $html = $this->navHtml();

        $this->assertStringContainsString('class="notification-list" role="list"', $html, 'the list needs role=list even when empty');
        $this->assertStringContainsString('class="notification-empty" role="listitem"', $html, 'the empty state is a list row too');
    }

    public function test_the_poll_js_keeps_the_dynamic_rows_in_the_a11y_tree(): void
    {
        // The blade writes the server rows with role="listitem"; the JS rebuilds
        // the list from JSON, so it must set the same role or the rebuilt rows
        // silently drop out of the list semantics.
        $js = file_get_contents(public_path('js/../../resources/views/layouts/navigation.blade.php'));

        $this->assertStringContainsString("a.setAttribute('role', 'listitem')", $js, 'a dynamically created row must keep role=listitem');
        $this->assertStringContainsString("div.setAttribute('role', 'listitem')", $js, 'the dynamic empty state must keep role=listitem');
        $this->assertStringContainsString('aria-expanded', $js, 'the poll must keep the bell state in sync');
    }

    public function test_the_chatbot_widget_is_a_named_dialog_the_keyboard_can_manage(): void
    {
        $html = $this->navHtml();

        $this->assertMatchesRegularExpression('/<button[^>]*id="chatbotToggle"[^>]*>/i', $html, 'the chatbot toggle must render');
        preg_match('/<button[^>]*id="chatbotToggle"[^>]*>/i', $html, $t);
        $this->assertStringContainsString('aria-label=', $t[0], 'the toggle needs a name — a robot glyph alone has none');
        $this->assertStringContainsString('aria-expanded', $t[0], 'the toggle must announce the dialog state');

        $this->assertMatchesRegularExpression('/<div[^>]*id="chatbotContainer"[^>]*>/i', $html, 'the chatbot container must render');
        preg_match('/<div[^>]*id="chatbotContainer"[^>]*>/i', $html, $c);
        $this->assertStringContainsString('role="dialog"', $c[0], 'the widget must be announced as a dialog');
        $this->assertStringContainsString('aria-modal="true"', $c[0], 'the widget must announce itself modal while open');
        $this->assertStringContainsString('aria-label="Trợ lý AI"', $c[0], 'the dialog must be announced by name');
    }

    public function test_the_chatbot_js_implements_escape_and_focus_return(): void
    {
        $js = file_get_contents(public_path('js/../../resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString("e.key === 'Escape'", $js, 'Escape must close the chatbot');
        $this->assertStringContainsString('closeChat()', $js, 'the Escape path must call the existing closer');
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded'", $js, 'the toggle state must stay in sync');
        $this->assertStringContainsString('toggle.focus()', $js, 'closing must return focus to the trigger');

        // The old 300ms deferred focus is gone — an immediate focus is what a
        // keyboard user waiting on the open is actually due.
        $this->assertStringNotContainsString('setTimeout(() => {', $js, 'the open-focus must not be deferred behind the CSS transition');
    }
}
