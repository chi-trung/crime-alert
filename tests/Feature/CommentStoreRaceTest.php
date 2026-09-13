<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewReplyOnComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #153: store()'s target check, the Comment::create, and the notify
 * were separate autocommitted statements, so a request that passed the
 * check while its alert/experience (or parent comment) was deleted
 * mid-flight landed a row against a missing target — an FK rejection that
 * surfaces as a raw 500 on MySQL (1452) and on SQLite alike (this repo's
 * connection enforces FKs by default, so both CI legs fail the same way;
 * the assertions below are on observable behavior regardless) — or
 * re-fetched a now-null $post in the notify block and dereferenced it
 * (NewReplyOnComment::toArray reads $post->id), or silently succeeded while
 * its bell and row belonged to a deleted thread. Reproduced with the
 * repo's own race idiom from LikeTest (#43/#139): a Comment::creating /
 * Comment::created hook that runs the whole delete inside the
 * check-to-insert / insert-to-notify window. The fix wraps the
 * create+notify sequence in a transaction whose target and parent current
 * reads back out with the same codes a pre-existing delete gets (403 for
 * the post via the #95 gate, 404 for a vanished parent via findOrFail)
 * before any notification can fire.
 */
class CommentStoreRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_deleted_mid_store_lands_no_orphan_row_or_bell(): void
    {
        // The target's complete delete (sweeps + row) lands after the
        // controller's exists()/approval checks and before the insert —
        // pre-fix the insert then violates the alert_id FK (500).
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        Comment::creating(function () use ($alert) {
            $alert->delete();
        });

        $this->actingAs($visitor)->post('/comments', ['content' => 'race', 'alert_id' => $alert->id])
            ->assertForbidden();

        // Pre-fix: 500 from the FK violation on the late insert.
        $this->assertSame(0, Comment::count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_experience_deleted_mid_store_lands_no_orphan_row_or_bell(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $experience = Experience::create(['user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'approved']);

        Comment::creating(function () use ($experience) {
            $experience->delete();
        });

        $this->actingAs($visitor)->post('/comments', ['content' => 'race', 'experience_id' => $experience->id])
            ->assertForbidden();

        $this->assertSame(0, Comment::count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_alert_deleted_after_the_insert_still_backs_out_of_the_reply(): void
    {
        // The other half of the window: the row writes fine, then the post
        // dies before the notify block re-fetches it. Pre-fix this reply
        // path hands NewReplyOnComment a null $post whose toArray() reads
        // $post->id -> 500 (the top-level branch fails silently with a
        // success flash on a dead thread instead; asserted below).
        $owner = User::factory()->create();
        $parentAuthor = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $parentAuthor->id, 'content' => 'top']);

        Comment::created(function () use ($alert) {
            $alert->delete();
        });

        $this->actingAs($visitor)->post('/comments', ['content' => 'reply', 'parent_id' => $parent->id])
            ->assertForbidden();

        // Pre-fix: 500 from the null-post deref inside the notification.
        $this->assertSame(0, Comment::where('content', 'reply')->count());
        $this->assertSame(0, DB::table('notifications')->where('type', NewReplyOnComment::class)->count());
        $this->assertSame(0, DB::table('notifications')->where('type', NewCommentOnPost::class)->count());
    }

    public function test_top_level_comment_on_alert_deleted_after_the_insert_redirects_cleanly(): void
    {
        // Same window, plain comment: pre-fix the post vanished between the
        // insert and the notify re-fetch, $post came back null, both bell
        // branches skipped silently, and the visitor still got the " Bình
        // luận đã được gửi!" success flash for a row the cascade had just
        // erased — the ghost-bell/success-on-dead-thread half of #153.
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        Comment::created(function () use ($alert) {
            $alert->delete();
        });

        $this->actingAs($visitor)->post('/comments', ['content' => 'race', 'alert_id' => $alert->id])
            ->assertForbidden();

        // Pre-fix this asserts as a 200-redirect success flash instead.
        $this->assertSame(0, Comment::count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_parent_comment_deleted_mid_store_lands_no_orphan_or_bell(): void
    {
        // Reply flow with the *parent* dying mid-flight instead of the post:
        // the parent_id FK rejects the insert (pre-fix 500). The endpoint's
        // own answer to an already-missing parent is findOrFail's 404, so
        // the raced delete must behave exactly like it.
        $owner = User::factory()->create();
        $parentAuthor = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $parentAuthor->id, 'content' => 'top']);

        Comment::creating(function () use ($parent) {
            $parent->delete();
        });

        $this->actingAs($visitor)->post('/comments', ['content' => 'reply', 'parent_id' => $parent->id])
            ->assertNotFound();

        // Pre-fix: 500 from the parent_id FK violation on the late insert.
        $this->assertSame(0, Comment::where('content', 'reply')->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $parentAuthor->id)->count());
    }

    public function test_normal_top_level_comment_still_creates_and_notifies(): void
    {
        // Positive control: the in-transaction re-check only fires on real
        // target death — a normal comment on a living approved alert
        // inserts once and sends exactly one NewCommentOnPost to the author.
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($visitor)->post('/comments', ['content' => 'fine', 'alert_id' => $alert->id])
            ->assertRedirect();

        $comment = Comment::where('content', 'fine')->firstOrFail();
        $this->assertSame($alert->id, $comment->alert_id);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $owner->id)->where('type', NewCommentOnPost::class)->count());
    }

    public function test_normal_reply_still_creates_and_notifies_parent_author(): void
    {
        $owner = User::factory()->create();
        $parentAuthor = User::factory()->create();
        $visitor = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $parentAuthor->id, 'content' => 'top']);

        $this->actingAs($visitor)->post('/comments', ['content' => 'reply', 'parent_id' => $parent->id])
            ->assertRedirect();

        $reply = Comment::where('content', 'reply')->firstOrFail();
        $this->assertSame($parent->id, $reply->parent_id);
        $this->assertSame($alert->id, $reply->alert_id);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $parentAuthor->id)->where('type', NewReplyOnComment::class)->count());
        // The reply branch notifies the parent's author only — never the
        // post author on top (the pre-fix elseif already guaranteed this;
        // the transaction rework must not double-bell).
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }
}
