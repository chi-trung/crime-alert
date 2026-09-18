<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #355: ChatbotAI::saveMessageHistory() sliced the last 50 messages and
 * then discarded the result — no store was ever written (no localStorage, by
 * requirement), so the method saved nothing while its name promised
 * persistence. Removed; the in-memory push stays because a future store would
 * want the trail already collected.
 *
 * The chatbot is an inline class inside layouts/app.blade.php rendered only for
 * authenticated users, so the assertions read the rendered page's inline
 * script rather than a separate file.
 */
class DeadChatbotSaveHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dead_save_message_history_is_gone(): void
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $js = $this->chatbotScript($user);

        // Assert the definition, not a bare token: the #355 comments
        // legitimately name the removed method while explaining the removal.
        $this->assertStringNotContainsString('saveMessageHistory() {', $js, 'the dead saver must be gone');
        $this->assertStringNotContainsString('.slice(-50)', $js, 'the discarded slice must be gone');
        $this->assertStringNotContainsString('this.saveMessageHistory();', $js, 'the call site must be gone');
    }

    public function test_the_message_trail_is_still_collected(): void
    {
        // The removal must not cost the in-memory history a future store
        // would need, nor the reset that keeps the array bounded per session.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $js = $this->chatbotScript($user);

        $this->assertStringContainsString('this.messageHistory.push(', $js, 'the trail push must survive');
        $this->assertStringContainsString('loadMessageHistory() {', $js, 'the loader must survive');
        $this->assertStringContainsString('this.messageHistory = [];', $js, 'the reset must survive');
    }

    public function test_chatbot_only_renders_for_authenticated_users(): void
    {
        // Pins the trust boundary the assertions above rely on: the class is
        // never shipped to a guest, so a guest page cannot carry the chatbot
        // script the tests read.
        $guest = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('class ChatbotAI', $guest, 'guests must not receive the chatbot');
    }

    /**
     * Extract the inline chatbot <script> so the assertions search real script
     * text rather than the whole page (which also carries Blade comments
     * naming the removed method).
     */
    private function chatbotScript(User $user): string
    {
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class ChatbotAI', $html, 'the chatbot class must render for an authed user');

        preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $m);

        return implode("\n", $m[1] ?? []);
    }
}
