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

        $this->startLog();
        $this->actingAs($user)->get("/alerts/{$alert->id}")->assertOk();
        $this->assertQueryOrdersBy('comments', ['created_at', 'id'], 'desc');
        DB::disableQueryLog();
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

        $this->startLog();
        $this->actingAs($user)->get("/experiences/{$exp->id}")->assertOk();
        $this->assertQueryOrdersBy('comments', ['created_at', 'id'], 'desc');
        DB::disableQueryLog();
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
        foreach (range(1, 3) as $i) {
            SupportMessage::forceCreate([
                'support_request_id' => $request->id, 'user_id' => $user->id, 'message' => "m{$i}",
            ]);
        }

        $this->startLog();
        $this->actingAs($user)->get("/support/{$request->id}")->assertOk();
        $this->assertQueryOrdersBy('support_messages', ['created_at', 'id'], 'asc');
        DB::disableQueryLog();
    }

    public function test_support_messages_ajax_orders_tied_messages_by_id_asc(): void
    {
        $user = User::factory()->create();
        $request = SupportRequest::forceCreate([
            'user_id' => $user->id, 'subject' => 'S', 'status' => 'open',
        ]);
        foreach (range(1, 3) as $i) {
            SupportMessage::forceCreate([
                'support_request_id' => $request->id, 'user_id' => $user->id, 'message' => "m{$i}",
            ]);
        }

        $this->startLog();
        $this->actingAs($user)->getJson("/support/{$request->id}/messages")->assertOk();
        $this->assertQueryOrdersBy('support_messages', ['created_at', 'id'], 'asc');
        DB::disableQueryLog();
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
}
