<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #390: the last five unnamed controls in the app. None was destructive
 * (the comment delete button of #388 was), but the chatbot close/send pair and
 * the dashboard refresh are controls a keyboard or screen-reader user reaches
 * with no announcement of what they do, and the guest logo link is the classic
 * "logo goes home" case.
 *
 * The chart menu test acts as an admin because the entire charts row sits
 * inside @if(auth()->user()->isAdmin) on the dashboard — a plain user never
 * sees the control — and a second test pins that gate.
 */
class UnlabeledButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chatbot_controls_are_named(): void
    {
        // Any page that extends layouts/app renders the chatbot widget.
        $html = $this->actingAs(User::factory()->create())
            ->get(route('alerts.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="chatbotClose"[^>]*aria-label="[^"]*"[^>]*>/i',
            $html,
            'the chatbot close button must announce its purpose'
        );
        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="chatbotSend"[^>]*aria-label="[^"]*"[^>]*>/i',
            $html,
            'the chatbot send button must announce its purpose'
        );
    }

    public function test_the_dashboard_refresh_button_is_named(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="refresh-btn"[^>]*aria-label="[^"]*"[^>]*>/i',
            $html,
            'the refresh button must announce its purpose'
        );
    }

    public function test_the_dashboard_chart_menu_is_named(): void
    {
        // The charts row — and this ellipsis menu with it — sits inside the
        // @if(auth()->user()->isAdmin) block opened at line 485, so a plain
        // user never reaches it. Only an admin can see the control.
        $admin = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'isAdmin' => true,
        ])->save());

        $html = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-bs-toggle="dropdown"[^>]*aria-label="[^"]*"[^>]*>/i',
            $html,
            'the chart options dropdown must announce its purpose'
        );
    }

    public function test_a_plain_user_does_not_see_the_admin_chart_menu(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'data-bs-toggle="dropdown"',
            $html,
            'the chart menu is admin-only — pin the gate so a future change that '
            .'labels the button cannot quietly widen who sees the admin charts'
        );
    }

    public function test_the_guest_logo_link_is_named(): void
    {
        // No shipped view extends layouts.guest today — the auth pages all
        // extend layouts.app — so this renders the layout directly. The logo
        // link is still reachable markup: the layout is the fallback any new
        // guest page inherits.
        $html = view('layouts.guest', ['slot' => ''])->render();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="\/"[^>]*aria-label="[^"]*"[^>]*>/i',
            $html,
            'the logo link on the guest layout must announce that it goes home'
        );

        // The svg itself has to be hidden now that the anchor carries the
        // name, otherwise it is announced as a second, empty image node.
        $this->assertMatchesRegularExpression(
            '/aria-hidden="true"/i',
            $html,
            'the logo svg must be hidden from the a11y tree'
        );
    }
}
