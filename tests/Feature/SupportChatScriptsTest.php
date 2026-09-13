<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #149: the live-chat script block sat in @push('scripts'), but
 * layouts/app.blade.php only has @yield('scripts') — no @stack('scripts')
 * exists anywhere, so the whole block (3s poll + AJAX submit handler) was
 * dropped from the rendered page. Now a proper @section('scripts') like
 * every other view; these tests assert the JavaScript actually ships.
 */
class SupportChatScriptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_page_ships_the_live_chat_script(): void
    {
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);

        $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('function fetchMessages()', false)
            ->assertSee('setInterval(fetchMessages, 3000)', false)
            ->assertDontSee("@push('scripts')", false);
    }

    public function test_admin_view_ships_the_same_script(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => User::factory()->create()->id, 'subject' => 'S']);

        $this->actingAs($admin)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('function fetchMessages()', false);
    }

    /**
     * Issue #206: the handler used to treat every response with res.ok as
     * a success — and fetch() follows the rejections' 302 transparently, so
     * the draft was always cleared even when the server refused the send.
     * These pin the shipped shape of the fix against the rendered page: the
     * rejection branch parses the JSON body and surfaces the server's
     * message, and the textarea is cleared only on the success arm. Read
     * the raw getContent() rather than assertSee: the script is plain text,
     * but assertions on it are clearer as exact substring matches.
     */
    public function test_chat_handler_surfaces_server_rejections_and_preserves_the_draft(): void
    {
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);

        $html = $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('showChatError', $html);
        $this->assertStringContainsString('res.json().catch(() => null)', $html);
        // The only textarea.value clear sits in the success arm, ahead of
        // the rejection branch — never before checking res.ok.
        $this->assertStringContainsString("if (res.ok) {\n                    textarea.value = '';", $html);
        $this->assertLessThan(
            strpos($html, 'res.json().catch'),
            strpos($html, "textarea.value = '';"),
            'draft must be cleared on the success arm, before the rejection branch runs'
        );
    }
}
