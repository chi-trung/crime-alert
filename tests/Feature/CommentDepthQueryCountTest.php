<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #258: #25's N+1 came back one level down. Both show pages eager-load
 * a FIXED depth-2 chain (['user','likes','replies.user','replies.likes']),
 * while comments/_item.blade.php recurses to unbounded depth — every comment
 * at depth >=3 dereferences three unloaded relations (user, likes, replies)
 * lazily, so each third-generation row costs 3 extra SELECTs (probe on
 * 265a988: 16 rows / 0 grandchildren = 23 queries, 24 grandchildren = 95,
 * 48 = 167 — exactly +3 per row). Depth-3 is ordinary UI: the "Trả lời"
 * button renders on every comment at every level.
 *
 * The fix replaces the fixed-depth chain with a FLAT fetch of the whole
 * thread (one query, ['user','likes'] eager) plus a PHP parent_id map the
 * partial recurses on, making the query count depth-invariant. Each test
 * pins the invariant by EQUALITY of query counts across two fixture sizes
 * (a dense thread vs the same roots + one thin thread) — not just under a
 * bound, because a bound would survive a regression that merely halves the
 * slope. The renders are then asserted to contain every generation, so the
 * equality can never be bought by truncating output.
 */
class CommentDepthQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function growThread(int $alertId, ?int $experienceId, Comment $root, int $spread, int $authorId): void
    {
        $post = $experienceId !== null
            ? ['experience_id' => $experienceId]
            : ['alert_id' => $alertId];
        $level = [$root];
        foreach ([2, 3] as $d) {
            $next = [];
            foreach ($level as $p) {
                for ($i = 0; $i < $spread; $i++) {
                    $c = Comment::create($post + [
                        'user_id' => $authorId,
                        'parent_id' => $p->id,
                        'content' => "marker-d{$d}-{$p->id}-{$i}",
                    ]);
                    $next[] = $c;
                }
            }
            $level = $next;
        }
        // one depth-4 leaf off the LAST depth-3 node of each branch chain:
        // cheap coverage of the deepest recursion the view must handle.
        foreach ($level as $c) {
            Comment::create($post + [
                'user_id' => $authorId,
                'parent_id' => $c->id,
                'content' => "marker-d4-{$c->id}",
            ]);
        }
    }

    /** @return array{0: int, 1: string} */
    private function measure(string $route, User $viewer): array
    {
        // /alerts/{alert} sits inside the 'auth' route group (guests get a
        // 302 to login); experiences are public but the viewer costs the
        // same, so one actingAs path serves both pages.
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($viewer)->get($route)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$count, $response->getContent()];
    }

    public function test_alert_show_query_count_is_invariant_under_comment_depth(): void
    {
        $author = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $author->id,
            'title' => 'A',
            'description' => 'd',
            'status' => 'approved',
        ]);
        $thin = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'content' => 'marker-root1']);
        $this->growThread($alert->id, null, $thin, spread: 1, authorId: $author->id);
        [$small, $smallHtml] = $this->measure(route('alerts.show', $alert), $author);

        $dense = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'content' => 'marker-root2']);
        $this->growThread($alert->id, null, $dense, spread: 5, authorId: $author->id);
        [$big, $bigHtml] = $this->measure(route('alerts.show', $alert), $author);

        // The dense fixture adds 5+25+25=55 rows BELOW the roots. If any
        // generation still dereferences lazily, $big > $small.
        $this->assertSame($small, $big, "query count grew with depth: {$small} -> {$big}");

        // Equality must not be bought by truncation: the deepest marker of
        // the dense tree (a depth-4 leaf) is present in the render.
        $this->assertStringContainsString('marker-root1', $smallHtml);
        $this->assertStringContainsString('marker-root2', $bigHtml);
        $deepest = Comment::where('content', 'like', 'marker-d4-%')->orderByDesc('id')->value('content');
        $this->assertNotNull($deepest);
        $this->assertStringContainsString($deepest, $bigHtml, 'the depth-4 leaf must still render');
    }

    public function test_experience_show_query_count_is_invariant_under_comment_depth(): void
    {
        $author = User::factory()->create();
        $experience = Experience::forceCreate([
            'user_id' => $author->id,
            'name' => 'N',
            'title' => 'T',
            'content' => 'c',
            'status' => 'approved',
        ]);
        $thin = Comment::create(['experience_id' => $experience->id, 'user_id' => $author->id, 'content' => 'marker-xroot1']);
        $this->growThread(0, $experience->id, $thin, spread: 1, authorId: $author->id);
        [$small] = $this->measure(route('experiences.show', $experience), $author);

        $dense = Comment::create(['experience_id' => $experience->id, 'user_id' => $author->id, 'content' => 'marker-xroot2']);
        $this->growThread(0, $experience->id, $dense, spread: 5, authorId: $author->id);
        [$big, $bigHtml] = $this->measure(route('experiences.show', $experience), $author);

        $this->assertSame($small, $big, "query count grew with depth: {$small} -> {$big}");
        $deepest = Comment::where('content', 'like', 'marker-d4-%')->orderByDesc('id')->value('content');
        $this->assertStringContainsString('marker-xroot2', $bigHtml);
        $this->assertStringContainsString($deepest, $bigHtml, 'the depth-4 leaf must still render');
    }

    public function test_thread_order_survives_the_in_memory_tree(): void
    {
        // #25's reply order (created_at ASC, id ASC tiebreak — #89) and
        // #89's top-level order (latest(), id DESC tiebreak) were properties
        // of the SQL relations; the PHP map must reproduce them. Same-second
        // inserts give the tiebreak cases for free (the fake clock does not
        // advance inside one request/section here).
        $author = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $author->id,
            'title' => 'A',
            'description' => 'd',
            'status' => 'approved',
        ]);
        $old = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'content' => 'marker-top-old']);
        // second-resolution created_at: siblings land in the same second, so the
        // visible order is decided by the id tiebreak in both directions.
        $new = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'content' => 'marker-top-new']);
        // replies of $old, ascending:
        $r1 = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'parent_id' => $old->id, 'content' => 'marker-rep-1']);
        $r2 = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'parent_id' => $old->id, 'content' => 'marker-rep-2']);
        unset($r1, $r2);

        [, $html] = $this->measure(route('alerts.show', $alert), $author);

        $posNew = strpos($html, 'marker-top-new');
        $posOld = strpos($html, 'marker-top-old');
        $posR1 = strpos($html, 'marker-rep-1');
        $posR2 = strpos($html, 'marker-rep-2');
        // Top-level renders latest() first: the NEWER root precedes the older.
        $this->assertTrue($posNew !== false && $posOld !== false && $posNew < $posOld,
            'top-level order (latest, id DESC) must survive the PHP-side tree');
        // Replies under their parent, oldest-first (created_at ASC, id ASC).
        $this->assertTrue($posR1 !== false && $posR2 !== false && $posR1 < $posR2,
            'reply order (#25/#89 ASC tiebreak) must survive the PHP-side tree');
        // And both replies sit inside their parent's block (after the older
        // root's marker, after the newer root entirely) — nesting, not
        // flattening.
        $this->assertTrue($posNew < $posOld && $posOld < $posR1 && $posR1 < $posR2);
    }
}
