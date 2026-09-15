<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #89: the tiebreak family's tail. #81 covered paginated lists,
 * #87 the dashboard picks — these six reads still ordered by
 * second-resolution created_at alone, so same-second rows (a comment
 * burst, two messages typed within one second) had no deterministic
 * order, which the AJAX feeds made visible live.
 *
 * These pin the emitted ORDER BY leg, not delivered row order: on sqlite
 * a tied set already comes back in insertion (== id-ascending) order, so
 * an ASC read's membership can't observe an id tiebreak and a delivered
 * -order assertion would pass even with the fix removed. The SQL string
 * cannot: without the orderByDesc/Asc('id') leg the logged query simply
 * lacks the second column. ORDER BY carries no bindings, so matching the
 * logged SQL is safe across both dialects (sqlite "" / mysql ``).
 *
 * Two of the seven (support page/ajax, #234) were later re-anchored to the
 * rendered sequence, and #258 moves the comment threads there too: once the
 * read itself stops spelling the order in SQL, the SQL string is no longer
 * an instrument for the invariant — the deterministic output is.
 */
class ThreadOrderTiebreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Assert some logged query over $table ordered by exactly the two
     * columns in $cols (both $dir), tolerating identifier quoting.
     *
     * @param  list<string>  $cols
     */
    private function assertQueryOrdersBy(string $table, array $cols, string $dir): void
    {
        $legs = [];
        foreach ($cols as $c) {
            $legs[] = "[\"`']?".preg_quote($c, '/')."[\"`']?\\s+".strtolower($dir);
        }
        $re = '/order by\s+'.implode('\s*,\s*', $legs).'\b/i';
        $hit = false;
        $tableRe = '/from\s+["`]?'.preg_quote($table, '/').'["`]?/i';
        foreach (DB::getQueryLog() as $entry) {
            if (preg_match($re, $entry['query']) && preg_match($tableRe, $entry['query'])) {
                $hit = true;
                break;
            }
        }
        $this->assertTrue(
            $hit,
            "no query over {$table} ordered by ".implode(' + ', $cols).' '.$dir.'; log had '
                .count(DB::getQueryLog()).' entr(y): '.implode(' || ', array_column(DB::getQueryLog(), 'query'))
        );
    }

    private function startLog(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
    }

    public function test_alert_thread_orders_tied_comments_by_id_desc(): void
    {
        $user = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        foreach (['AAA', 'BBB', 'CCC'] as $marker) {
            Comment::forceCreate([
                'alert_id' => $alert->id, 'user_id' => $user->id, 'content' => "c-{$marker}",
            ]);
        }

        // Issue #258 re-anchor (same move as the support tests below): the
        // top-level DESC is no longer an SQL leg — the thread is fetched flat
        // ASC and the roots bucket is reversed in PHP — so what #89 protects,
        // tied comments rendering in one deterministic latest-first order,
        // is pinned at the rendered sequence instead of one SQL spelling.
        $html = $this->actingAs($user)->get("/alerts/{$alert->id}")->assertOk()->getContent();
        $positions = array_map(fn ($m) => strpos($html, $m), ['c-CCC', 'c-BBB', 'c-AAA']);
        $this->assertSame([true, true, true], array_map(fn ($p) => $p !== false, $positions));
        $this->assertTrue($positions[0] < $positions[1] && $positions[1] < $positions[2],
            'tied top-level comments must render newest-first (id DESC), deterministically');
    }

    public function test_experiences_thread_orders_tied_comments_by_id_desc(): void
    {
        $user = User::factory()->create();
        $exp = Experience::forceCreate([
            'user_id' => $user->id, 'name' => 'N', 'title' => 'E', 'content' => 'c', 'status' => 'approved',
        ]);
        foreach (range(1, 3) as $i) {
            Comment::forceCreate([
                'experience_id' => $exp->id, 'user_id' => $user->id, 'content' => "x-{$i}",
            ]);
        }

        // Issue #258 re-anchor — see the alerts twin above.
        $html = $this->actingAs($user)->get("/experiences/{$exp->id}")->assertOk()->getContent();
        $positions = array_map(fn ($m) => strpos($html, $m), ['x-3', 'x-2', 'x-1']);
        $this->assertSame([true, true, true], array_map(fn ($p) => $p !== false, $positions));
        $this->assertTrue($positions[0] < $positions[1] && $positions[1] < $positions[2],
            'tied top-level comments must render newest-first (id DESC), deterministically');
    }

    public function test_replies_relation_orders_tied_replies_by_id_asc(): void
    {
        $user = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        $parent = Comment::forceCreate([
            'alert_id' => $alert->id, 'user_id' => $user->id, 'content' => 'p',
        ]);
        foreach (range(1, 3) as $i) {
            Comment::forceCreate([
                'alert_id' => $alert->id, 'user_id' => $user->id, 'content' => "r{$i}", 'parent_id' => $parent->id,
            ]);
        }

        // The relation's declared order is consumed both lazily and via the
        // eager loads on the show pages; force the SQL out through the lazy
        // getter, which runs replies()->get().
        $this->startLog();
        $parent->replies()->pluck('id');
        $this->assertQueryOrdersBy('comments', ['created_at', 'id'], 'asc');
        DB::disableQueryLog();
    }

    public function test_support_messages_page_orders_tied_messages_by_id_asc(): void
    {
        $user = User::factory()->create();
        $request = SupportRequest::forceCreate([
            'user_id' => $user->id, 'subject' => 'S', 'status' => 'open',
        ]);
        $ids = [];
        foreach (range(1, 3) as $i) {
            $msg = SupportMessage::forceCreate([
                'support_request_id' => $request->id, 'user_id' => $user->id, 'message' => "m{$i}",
            ]);
            $ids[] = $msg->id;
        }

        // Issue #234 re-anchor: the initial render is now the latest-100
        // window read DESC and reversed, so the query log no longer contains
        // a literal "created_at asc, id asc". What #89 actually protects is
        // the OUTPUT order — tied rows must never swap between renders — so
        // pin the rendered sequence instead of one SQL spelling. The id
        // tiebreak itself is re-pinned below via the AJAX response.
        // Issue #283: the expected sequence is the real inserted ids (the
        // invariant is their ASC order, not their values — assuming ids
        // start at 1 was sqlite-only; MySQL's counter does not rewind).
        $rendered = $this->actingAs($user)->get("/support/{$request->id}")->assertOk()
            ->viewData('messages')->pluck('id')->all();
        $this->assertSame($ids, $rendered);
        $this->assertSame(collect($ids)->sort()->values()->all(), $rendered,
            'the rendered sequence must be id-ascending');
    }

    public function test_support_messages_ajax_orders_tied_messages_by_id_asc(): void
    {
        $user = User::factory()->create();
        $request = SupportRequest::forceCreate([
            'user_id' => $user->id, 'subject' => 'S', 'status' => 'open',
        ]);
        $inserted = [];
        foreach (range(1, 3) as $i) {
            $msg = SupportMessage::forceCreate([
                'support_request_id' => $request->id, 'user_id' => $user->id, 'message' => "m{$i}",
            ]);
            $inserted[] = $msg->id;
        }

        // Issue #234 re-anchor (see the page test): the polled feed keeps
        // #89's promise at the observable boundary — same-second rows come
        // back id-ascending, oldest-first, deterministically. Issue #283:
        // expectation is the real inserted ids in ascending order (the old
        // literal [1,2,3] assumed the allocator starts at 1 — sqlite-only;
        // the MySQL probe returned [617,618,619]).
        $ids = $this->actingAs($user)->getJson("/support/{$request->id}/messages")->assertOk()
            ->json('messages');
        $returned = collect($ids)->pluck('id')->all();
        $this->assertSame($inserted, $returned);
        $this->assertSame(collect($inserted)->sort()->values()->all(), $returned,
            'the feed must be id-ascending regardless of where the counter sat');
    }

    public function test_unread_dropdown_preview_orders_by_id_desc(): void
    {
        $user = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        foreach (range(1, 12) as $i) {
            $user->notify(new NewPostNotification($alert, $user, 'alert'));
        }

        $this->startLog();
        $this->actingAs($user)->getJson('/notifications/unread')->assertOk();
        // The dropdown is a take(10); the framework relation ends in
        // ->latest() (created_at desc only), so the controller's added id
        // leg is what makes the truncation deterministic.
        $this->assertQueryOrdersBy('notifications', ['created_at', 'id'], 'desc');
        DB::disableQueryLog();
    }

    public function test_navigation_bell_preview_orders_by_id_desc(): void
    {
        // Issue #91: layouts/navigation.blade.php carries a second copy of
        // the take(10) preview query and renders inside layouts.app on every
        // authenticated page — the AJAX-endpoint fix above never covered it.
        $user = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        foreach (range(1, 12) as $i) {
            $user->notify(new NewPostNotification($alert, $user, 'alert'));
        }

        $this->startLog();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->assertQueryOrdersBy('notifications', ['created_at', 'id'], 'desc');
        DB::disableQueryLog();
    }
}
