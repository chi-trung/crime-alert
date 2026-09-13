<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #182: resources/views/support/show.blade.php loads
 * public/js/support_show.js synchronously at the TOP of the content section,
 * but the script's targets — #chat-messages and #chat-input — are parsed
 * further down the same document. A classic <script> blocks the parser, so
 * the file's original top-level getElementById calls always returned null,
 * the ?./if guards swallowed it silently, and autofocus plus the initial
 * scroll-to-bottom never ran on any browser from the file's first commit
 * (the inline @section('scripts') fetchMessages early-returns on load, so no
 * other path rescues it). The handler now waits for DOMContentLoaded like
 * alerts_show.js / dashboard.js — with placement no longer mattering, the
 * load tag may stay in the head of the content. These tests pin the JS's
 * deferred shape: registration first, DOM lookups only inside it.
 */
class SupportShowScrollTest extends TestCase
{
    use RefreshDatabase;

    private function script(): string
    {
        $js = file_get_contents(public_path('js/support_show.js'));
        $this->assertNotFalse($js, 'public/js/support_show.js must exist and be readable.');

        return $js;
    }

    public function test_dom_lookups_happen_after_dom_content_loaded_not_at_parse_time(): void
    {
        $js = $this->script();

        // The old bug was getElementById executing at parse time. Assert the
        // FIRST DOM query in the file sits inside a DOMContentLoaded handler.
        $dcl = strpos($js, "document.addEventListener('DOMContentLoaded'");
        $firstLookup = strpos($js, 'document.getElementById');
        $this->assertNotFalse($dcl, 'script must defer to DOMContentLoaded');
        $this->assertNotFalse($firstLookup);
        $this->assertGreaterThan($dcl, $firstLookup,
            'no getElementById may run at parse time — every lookup must live inside the DOMContentLoaded handler');

        // No optional-chaining top-level idiom left from the broken version.
        $this->assertStringNotContainsString("document.getElementById('chat-input')?.focus()", $js);
    }

    public function test_script_still_ships_autofocus_and_scroll_behaviour(): void
    {
        $js = $this->script();

        $this->assertStringContainsString("getElementById('chat-input')", $js);
        $this->assertStringContainsString('chatInput.focus()', $js);
        $this->assertStringContainsString("getElementById('chat-messages')", $js);
        $this->assertStringContainsString('chatBox.scrollTop = chatBox.scrollHeight', $js);
    }

    public function test_support_page_still_loads_the_script(): void
    {
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);

        // The deferred handler is load-order independent, so keeping the tag
        // where it is stays fine — pin that it did not silently vanish.
        $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('js/support_show.js', false)
            ->assertSee('id="chat-messages"', false)
            ->assertSee('id="chat-input"', false);
    }
}
