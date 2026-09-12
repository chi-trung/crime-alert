<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards against the N+1 family fixed in issue #25: list pages that render
 * $row->user->name per row, and comment threads that per-row fetch the
 * author, the viewer's like, the like count and the replies collection.
 * Fixtures are seeded OUTSIDE the timed window; only the HTTP request is
 * counted. The assertion style is a bound, not an exact count: growth beyond
 * a small constant means a per-row query came back.
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Execute a request through the given closure and count SQL queries.
     * Auth/session middleware must already be arranged by the caller.
     */
    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Create $count approved alerts, each with its own author — the condition
     * that turns a missing with('user') into one SELECT per row.
     */
    private function seedAlertsWithAuthors(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Alert::create([
                'user_id' => User::factory()->create()->id,
                'title' => "Alert {$i}",
                'description' => 'd',
                'status' => 'approved',
            ]);
        }
    }

    public function test_alerts_index_query_count_does_not_grow_with_rows(): void
    {
        $user = User::factory()->create();

        $this->seedAlertsWithAuthors(1);
        $few = $this->countQueries(fn () => $this->actingAs($user)->get('/alerts'));

        $this->seedAlertsWithAuthors(9);
        $many = $this->countQueries(fn () => $this->actingAs($user)->get('/alerts'));

        $this->assertTrue(
            $many <= $few + 1,
            "alerts index scales with row count: 1 row = {$few} queries, 10 rows = {$many} queries"
        );
    }

    public function test_admin_alerts_index_query_count_does_not_grow_with_rows(): void
    {
        $admin = User::factory()->admin()->create();

        $this->seedAlertsWithAuthors(1);
        $few = $this->countQueries(fn () => $this->actingAs($admin)->get('/admin/alerts'));

        $this->seedAlertsWithAuthors(14);
        $many = $this->countQueries(fn () => $this->actingAs($admin)->get('/admin/alerts'));

        $this->assertTrue(
            $many <= $few + 1,
            "admin alerts index scales with row count: 1 row = {$few} queries, 15 rows = {$many} queries"
        );
    }

    public function test_experiences_index_query_count_does_not_grow_with_rows(): void
    {
        $seed = function (int $count) {
            for ($i = 0; $i < $count; $i++) {
                Experience::create([
                    'user_id' => User::factory()->create()->id,
                    'name' => 'N',
                    'title' => 't',
                    'content' => 'c',
                    'status' => 'approved',
                ]);
            }
        };

        $seed(1);
        $few = $this->countQueries(fn () => $this->get('/experiences'));

        $seed(8);
        $many = $this->countQueries(fn () => $this->get('/experiences'));

        $this->assertTrue(
            $many <= $few + 1,
            "experiences index scales with row count: 1 row = {$few} queries, 9 rows = {$many} queries"
        );
    }

    public function test_support_admin_index_query_count_does_not_grow_with_rows(): void
    {
        $admin = User::factory()->admin()->create();

        SupportRequest::create(['user_id' => User::factory()->create()->id, 'subject' => 's']);
        $few = $this->countQueries(fn () => $this->actingAs($admin)->get('/admin/support'));

        for ($i = 0; $i < 9; $i++) {
            SupportRequest::create(['user_id' => User::factory()->create()->id, 'subject' => 's']);
        }
        $many = $this->countQueries(fn () => $this->actingAs($admin)->get('/admin/support'));

        $this->assertTrue(
            $many <= $few + 1,
            "support admin index scales with row count: 1 row = {$few} queries, 10 rows = {$many} queries"
        );
    }

    /**
     * Thread of $topLevel comments with $repliesEach replies apiece; the
     * viewer liked every top-level comment, which is the worst case for the
     * per-row like check the old view did.
     */
    private function seedCommentThread(Alert $alert, int $topLevel, int $repliesEach, User $viewer): void
    {
        for ($i = 0; $i < $topLevel; $i++) {
            $comment = Comment::create([
                'alert_id' => $alert->id,
                'user_id' => User::factory()->create()->id,
                'content' => "top {$i}",
            ]);
            $comment->likes()->create(['user_id' => $viewer->id]);
            $comment->likes()->create(['user_id' => User::factory()->create()->id]);
            for ($r = 0; $r < $repliesEach; $r++) {
                $reply = Comment::create([
                    'alert_id' => $alert->id,
                    'user_id' => User::factory()->create()->id,
                    'content' => "reply {$r}",
                    'parent_id' => $comment->id,
                ]);
                $reply->likes()->create(['user_id' => User::factory()->create()->id]);
            }
        }
    }

    public function test_alert_show_comment_thread_query_count_is_bounded(): void
    {
        $viewer = User::factory()->create();

        $smallAlert = Alert::create(['user_id' => $viewer->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $this->seedCommentThread($smallAlert, 1, 1, $viewer);
        $small = $this->countQueries(fn () => $this->actingAs($viewer)->get("/alerts/{$smallAlert->id}")->assertOk());

        $largeAlert = Alert::create(['user_id' => $viewer->id, 'title' => 'B', 'description' => 'd', 'status' => 'approved']);
        $this->seedCommentThread($largeAlert, 6, 2, $viewer);
        $large = $this->countQueries(fn () => $this->actingAs($viewer)->get("/alerts/{$largeAlert->id}")->assertOk());

        // 18 comment rows vs 2 rows. With user/likes/replies eager-loaded in
        // batches the delta is a constant handful of queries, not ~4 per row;
        // before the fix this ratio was roughly 9:1.
        $this->assertTrue(
            $large <= $small + 20,
            "comment thread scales with comment count: 2 rows = {$small} queries, 18 rows = {$large} queries"
        );
    }

    public function test_experience_show_comment_thread_query_count_is_bounded(): void
    {
        $user = User::factory()->create();

        $seed = function (string $title, int $topLevel, int $repliesEach) use ($user) {
            $experience = Experience::create([
                'user_id' => $user->id, 'name' => 'N', 'title' => $title, 'content' => 'c', 'status' => 'approved',
            ]);
            for ($i = 0; $i < $topLevel; $i++) {
                $comment = Comment::create([
                    'experience_id' => $experience->id,
                    'user_id' => User::factory()->create()->id,
                    'content' => "top {$i}",
                ]);
                $comment->likes()->create(['user_id' => $user->id]);
                for ($r = 0; $r < $repliesEach; $r++) {
                    Comment::create([
                        'experience_id' => $experience->id,
                        'user_id' => User::factory()->create()->id,
                        'content' => "reply {$r}",
                        'parent_id' => $comment->id,
                    ]);
                }
            }

            return $experience;
        };

        $small = $seed('S', 1, 1);
        $few = $this->countQueries(fn () => $this->actingAs($user)->get("/experiences/{$small->id}")->assertOk());

        $large = $seed('L', 6, 2);
        $many = $this->countQueries(fn () => $this->actingAs($user)->get("/experiences/{$large->id}")->assertOk());

        $this->assertTrue(
            $many <= $few + 20,
            "experience comment thread scales with comment count: 2 rows = {$few} queries, 18 rows = {$many} queries"
        );
    }
}
