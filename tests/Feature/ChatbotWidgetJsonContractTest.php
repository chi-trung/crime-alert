<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #295 (CHATBOT-1): #207 made stale-session JSON answers carry
 * {success, message, redirect}, and #208 made the chatbot widget parse the
 * error body before throwing — but the two fixes never met: the widget's
 * fetch declares Content-Type only, no Accept. Laravel's expectsJson() is
 * false for the browser's default wildcard accept header (the central
 * renderer in bootstrap/app.php stays silent), so a stale session got the
 * Authenticate middleware's 302 to the HTML login page, fetch transparently
 * FOLLOWED the redirect, response.ok was true (login page 200),
 * response.json() threw on HTML, and the user saw the generic dead
 * "connection error" bubble. Every #207/#208 guarantee — 401 redirect
 * contract, 429 lane message, 422 shape — was unreachable from the one
 * endpoint the #208 comment is about. The two sibling clients
 * (public/js/alerts_show.js, experiences_show.js) send
 * 'Accept': 'application/json' and branch on data.redirect; the widget must
 * join that contract.
 */
class ChatbotWidgetJsonContractTest extends TestCase
{
    use RefreshDatabase;

    private function widgetHtml(): string
    {
        return $this->actingAs(User::factory()->create())
            ->get('/alerts')
            ->assertOk()
            ->getContent();
    }

    public function test_widget_fetch_declares_the_json_accept_header(): void
    {
        $html = $this->widgetHtml();
        $fetch = strpos($html, 'const response = await fetch(');
        $this->assertNotFalse($fetch, 'the chatbot widget must ship its fetch call');

        // Scoped to THIS request's headers block, so the pin cannot be
        // satisfied by some unrelated fetch elsewhere on the page.
        $block = substr($html, $fetch, strpos($html, 'body: JSON.stringify', $fetch) - $fetch);
        $this->assertStringContainsString(
            "'Accept': 'application/json'",
            $block,
            'without Accept, expectsJson() is false and the #207 JSON contract is unreachable'
        );
    }

    public function test_widget_follows_the_redirect_branch_of_its_siblings(): void
    {
        $html = $this->widgetHtml();
        $guard = strpos($html, 'if (!response.ok) {');
        $this->assertNotFalse($guard);
        $block = substr($html, $guard, strpos($html, 'throw new Error(`HTTP', $guard) - $guard);

        // Same consumption shape as alerts_show.js / experiences_show.js.
        $this->assertStringContainsString('data.redirect', $block, 'a 401/403 answer must be acted on, not just displayed');
        $this->assertStringContainsString('window.location.href', $block);
    }

    public function test_chat_input_caps_length_at_the_server_side_2000_rule(): void
    {
        // ChatbotController::ask validates 'question' => max:2000; without a
        // matching maxlength, pasting a longer question burns a chatbot-lane
        // request on a 422 the bubble can only display generically.
        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="chatbotInput"[^>]*maxlength="2000"/s',
            $this->widgetHtml(),
            'the input must mirror the server-side max:2000'
        );
    }

    public function test_without_the_header_a_stale_session_ask_redirects_as_html(): void
    {
        // Control (passes on main): proves WHY the Accept pin above matters —
        // a plain POST never sees the JSON contract, it gets the middleware's
        // 302 that fetch silently follows into an unparseable HTML page.
        $this->post('/chatbot/ask', ['question' => 'hi'])
            ->assertRedirect(route('login'));
    }

    public function test_with_the_header_the_ask_answers_the_json_contract(): void
    {
        // Control (passes on main): postJson() sends the very header the
        // widget now adds — the #207 shape the redirect branch consumes.
        $this->postJson('/chatbot/ask', ['question' => 'hi'])
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'redirect' => route('login'),
            ]);
    }
}
