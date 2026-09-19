<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #402: two chat regions update from a poll but were not live regions,
 * so a screen reader user never learned a reply had arrived.
 *
 * The support thread (`support/show.blade.php`) polls `?after_id=` every 3s and
 * appends bubbles; the chatbot (`layouts/app.blade.php`) writes the AI's reply
 * into its own messages node. Neither region carried `aria-live` or `role`,
 * so the dynamic content appeared in total silence — the same gap #400 closed
 * for the notification badge.
 *
 * The markup is the contract: a screen reader reads the rendered page, not the
 * JS that mutates it, so the attributes must ship in the blade. The dynamic
 * builders must also set the same role as the server rows, or a rebuilt region
 * silently drops out of the semantics it was given.
 */
class ChatLiveRegionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_support_thread_announces_new_messages(): void
    {
        $user = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $user->id, 'subject' => 'S']);

        $html = $this->actingAs($user)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="chat-messages"[^>]*>/i',
            $html,
            'the support chat region must render'
        );
        preg_match('/<div[^>]*id="chat-messages"[^>]*>/i', $html, $m);
        $region = $m[0];

        $this->assertStringContainsString('role="log"', $region, 'a chat history is an ordered log region');
        $this->assertStringContainsString('aria-live="polite"', $region, 'appended replies must be announced');
        $this->assertStringContainsString('aria-label="Hội thoại hỗ trợ"', $region, 'the region must be announced by name');
    }

    public function test_the_support_rows_ship_list_semantics(): void
    {
        $user = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $user->id, 'subject' => 'S']);
        // An empty thread renders no rows at all, so seed one real message to
        // exercise the populated branch the pin is about.
        SupportMessage::create([
            'support_request_id' => $thread->id,
            'user_id' => $user->id,
            'message' => 'Nội dung tin nhắn',
        ]);

        $html = $this->actingAs($user)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'data-msg-id=',
            $html,
            'the server rows must render so the role pin below is meaningful'
        );
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="support-chat-msg[^"]*"[^>]*data-msg-id="\d+"[^>]*role="listitem"[^>]*>/',
            $html,
            'a server-rendered row must carry role=listitem'
        );
    }

    public function test_the_poll_builder_keeps_the_row_semantics(): void
    {
        // The server rows ship role="listitem"; buildBubble() rebuilds the same
        // node from JSON, so it must set the same role or the appended rows
        // drop out of the region's structure.
        $js = file_get_contents(base_path('resources/views/support/show.blade.php'));

        $this->assertStringContainsString(
            "wrapper.setAttribute('role', 'listitem')",
            $js,
            'a dynamically built row must keep role=listitem'
        );
    }

    public function test_the_chatbot_replies_are_announced(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="chatbotMessages"[^>]*>/i',
            $html,
            'the chatbot messages region must render'
        );
        preg_match('/<div[^>]*id="chatbotMessages"[^>]*>/i', $html, $m);
        $region = $m[0];

        $this->assertStringContainsString('role="log"', $region, 'the AI reply history is a log region');
        $this->assertStringContainsString('aria-live="polite"', $region, 'an AI reply must be announced');
        $this->assertStringContainsString('aria-label="Hội thoại với trợ lý AI"', $region, 'the region must be announced by name');
    }
}
