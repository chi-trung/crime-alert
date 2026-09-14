<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #234: the live-chat poll on GET /support/{id}/messages ran an
 * uncapped ->get() over the whole thread, every 3 seconds, per open tab,
 * with no throttle middleware — the read-unbounded class (#67/#75) applied
 * to the app's most frequent background request. The fix is three bounded
 * shapes on messagesAjax (after_id delta / before_id "load older" /
 * latest-100 initial window) + a same window on show() + an own named
 * throttle lane on the route (the #147 rule: inline limiters need the third
 * arg or they share one counter).
 */
class SupportPollDeltaFeedTest extends TestCase
{
    use RefreshDatabase;

    private function thread(int $messages = 0): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Thread']);
        for ($i = 1; $i <= $messages; $i++) {
            SupportMessage::create([
                'support_request_id' => $thread->id,
                'user_id' => $i % 3 === 0 ? $admin->id : $owner->id,
                'message' => 'msg-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        return [$owner, $admin, $thread];
    }

    public function test_delta_poll_returns_only_messages_newer_than_after_id(): void
    {
        [$owner, , $thread] = $this->thread(5);

        $response = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread, 'after_id' => 3]))
            ->assertOk();

        $ids = collect($response->json('messages'))->pluck('id')->all();
        $this->assertSame([4, 5], $ids, 'delta must ship only rows above after_id, oldest-first');
        $this->assertSame(5, $response->json('latest_id'));
    }

    public function test_idle_tab_pays_one_bounded_empty_read(): void
    {
        // The headline regression: an idle tab (cursor at the newest row)
        // used to transfer the entire history every tick. Now it must get an
        // empty delta AND the messages select must carry a LIMIT.
        [$owner, , $thread] = $this->thread(300);
        $last = SupportMessage::where('support_request_id', $thread->id)->max('id');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread, 'after_id' => $last]))
            ->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $response->json('messages'));
        // Cursor still advances/holds so the client never regresses.
        $this->assertSame($last, $response->json('latest_id'));

        $msgQuery = collect($queries)
            ->first(fn ($q) => str_contains($q['query'], 'support_messages'));
        $this->assertNotNull($msgQuery, 'expected a support_messages read');
        $this->assertStringContainsStringIgnoringCase('limit', $msgQuery['query'],
            'the delta read must be bounded — an unbounded WHERE-only read is the #234 bug');
        // Cursor-keyed read (dialect-quote-agnostic: SQLite "id" > ?, MySQL
        // backticks): a greater-than comparison bound to the after_id value.
        $this->assertMatchesRegularExpression('/>\s*\?/i', $msgQuery['query']);
        $this->assertContains($last, $msgQuery['bindings']);
    }

    public function test_full_load_is_capped_at_the_latest_100_in_both_channels(): void
    {
        [$owner, , $thread] = $this->thread(150);

        $json = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread]))
            ->assertOk()
            ->assertJsonCount(100, 'messages')
            ->json();
        $ids = collect($json['messages'])->pluck('id')->all();
        $this->assertSame(range(51, 150), $ids, 'window = newest 100, rendered oldest-first');
        $this->assertSame(150, $json['latest_id']);
        $this->assertSame(51, $json['oldest_id']);
        $this->assertTrue($json['has_more_older']);

        // show() renders the same window, not the whole thread.
        $html = $this->actingAs($owner)->get(route('support.show', $thread))->assertOk()->getContent();
        $this->assertSame(100, substr_count($html, 'class="support-chat-msg'));
        $this->assertStringContainsString('msg-051', $html);
        $this->assertStringNotContainsString('msg-050', $html);
        $this->assertStringContainsString('id="load-older"', $html, 'long threads must offer the rest');

        // #89's guarantee survives the window shape: the DESC twin of the old
        // ASC query must keep BOTH order legs, or same-second rows swap at
        // the window edge between renders (ThreadOrderTiebreakTest re-anchors
        // the output side; this pins the SQL side #234 changed).
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($owner)->getJson(route('support.messages', ['supportRequest' => $thread]))->assertOk();
        $window = collect(DB::getQueryLog())
            ->first(fn ($q) => str_contains($q['query'], 'support_messages') && str_contains($q['query'], 'order by'));
        DB::disableQueryLog();
        $this->assertMatchesRegularExpression('/created_at["`\' ]+desc\s*,\s*["`\' ]?id["`\' ]+desc/i', $window['query']);
    }

    public function test_short_threads_offer_no_load_older_and_render_everything(): void
    {
        [$owner, , $thread] = $this->thread(3);

        $json = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread]))
            ->assertOk()->json();
        $this->assertSame([1, 2, 3], collect($json['messages'])->pluck('id')->all());
        $this->assertFalse($json['has_more_older']);

        $html = $this->actingAs($owner)->get(route('support.show', $thread))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="load-older"', $html);
        $this->assertSame(3, substr_count($html, 'class="support-chat-msg'));
    }

    public function test_before_id_pages_the_window_backwards(): void
    {
        [$owner, , $thread] = $this->thread(150);

        // One page older than the visible window's first row (id 51) is
        // capped at 100 too: rows 1..50 — with nothing left below.
        $json = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread, 'before_id' => 51]))
            ->assertOk()->json();
        $this->assertSame(range(1, 50), collect($json['messages'])->pluck('id')->all());
        $this->assertSame(1, $json['oldest_id']);
        $this->assertFalse($json['has_more_older']);

        // From the window's own oldest_id, a full 100-row page still fits.
        $json2 = $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread, 'before_id' => 151]))
            ->assertOk()->json();
        $this->assertCount(100, $json2['messages']);
        $this->assertTrue($json2['has_more_older']);
    }

    public function test_poll_lane_throttles_and_is_isolated_from_the_write_lanes(): void
    {
        // 30/min budget: 30 polls pass, the 31st is 429. Then the sendMessage
        // ('support') lane must still be live — same-lane-name collision is
        // exactly the bug #147 recorded when an inline limiter forgot its
        // third argument.
        [$owner, , $thread] = $this->thread(2);
        $url = route('support.messages', ['supportRequest' => $thread]);

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($owner)->getJson($url)->assertOk();
        }
        $this->actingAs($owner)->getJson($url)->assertStatus(429);

        $this->actingAs($owner)->postJson(route('support.sendMessage', $thread), ['message' => 'still fine'])
            ->assertSuccessful();
    }
}
