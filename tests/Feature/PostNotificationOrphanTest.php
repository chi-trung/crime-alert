<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewReplyOnComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #121: the six post notification classes point at posts/comments only
 * inside their JSON data payload — the morph notifiable_* pair keys the
 * recipient, so no FK cascades them (same structural reason as #57/#61 and
 * the #102 support sweep). Deleting a post or comment must sweep exactly its
 * own notification rows; survivors must keep working links.
 */
class PostNotificationOrphanTest extends TestCase
{
    use RefreshDatabase;

    private function orphanRows(): int
    {
        return DB::table('notifications')
            ->whereIn('type', [
                NewPostNotification::class,
                NewPostPendingApprovalNotification::class,
                LikePostNotification::class,
                NewCommentOnPost::class,
                NewReplyOnComment::class,
                LikeCommentNotification::class,
            ])
            ->count();
    }

    public function test_deleting_an_alert_sweeps_its_notifications_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $other->id, 'content' => 'c']);
        // Every payload shape that names this post: new-post fanout, pending
        // approval, like, comment, reply, comment-like.
        $other->notify(new NewPostNotification($alert, $owner, 'alert'));
        $other->notify(new NewPostPendingApprovalNotification($alert, $owner, 'alert'));
        $owner->notify(new LikePostNotification($other, $alert, 'alert'));
        $owner->notify(new NewCommentOnPost($comment, $alert, 'alert'));
        $owner->notify(new NewReplyOnComment($comment, $comment, $alert, 'alert'));
        $other->notify(new LikeCommentNotification($other, $comment, $alert, 'alert'));
        $this->assertSame(6, $this->orphanRows());

        // An unrelated alert's rows must survive the sweep.
        $survivor = Alert::create(['user_id' => $owner->id, 'title' => 'S', 'description' => 'd', 'status' => 'approved']);
        $other->notify(new NewPostNotification($survivor, $owner, 'alert'));
        $this->assertSame(7, $this->orphanRows());

        $alert->delete();

        $this->assertSame(1, $this->orphanRows());
        $row = DB::table('notifications')->sole();
        $this->assertStringContainsString('"post_id":'.$survivor->id.',', (string) $row->data);
    }

    public function test_alert_sweep_does_not_eat_same_id_experience_rows(): void
    {
        // post_type scopes the sweep: alert id 1 and experience id 1 collide
        // across the two tables, so the alert delete must not touch rows
        // naming the experience.
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $exp = Experience::create(['user_id' => $owner->id, 'title' => 'E', 'content' => 'c', 'name' => 'N', 'status' => 'approved']);
        $this->assertSame($alert->id, $exp->id);
        $owner->notify(new LikePostNotification($other, $alert, 'alert'));
        $owner->notify(new LikePostNotification($other, $exp, 'experience'));
        $this->assertSame(2, $this->orphanRows());

        $alert->delete();

        $this->assertSame(1, $this->orphanRows());
        $row = DB::table('notifications')->sole();
        $this->assertStringContainsString('"post_type":"experience"', (string) $row->data);
    }

    public function test_deleting_an_experience_sweeps_its_notifications_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $exp = Experience::create(['user_id' => $owner->id, 'title' => 'T', 'content' => 'c', 'name' => 'N', 'status' => 'approved']);
        $comment = Comment::create(['experience_id' => $exp->id, 'user_id' => $other->id, 'content' => 'c']);
        $other->notify(new NewPostNotification($exp, $owner, 'experience'));
        $owner->notify(new LikePostNotification($other, $exp, 'experience'));
        $owner->notify(new NewCommentOnPost($comment, $exp, 'experience'));
        $this->assertSame(3, $this->orphanRows());

        $exp->delete();

        $this->assertSame(0, $this->orphanRows());
    }

    public function test_deleting_a_comment_sweeps_its_subtree_notifications_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'p']);
        $child = Comment::create(['alert_id' => $alert->id, 'parent_id' => $parent->id, 'user_id' => $other->id, 'content' => 'c']);
        $owner->notify(new NewCommentOnPost($parent, $alert, 'alert'));
        $owner->notify(new NewReplyOnComment($child, $parent, $alert, 'alert'));
        $other->notify(new LikeCommentNotification($other, $child, $alert, 'alert'));
        // A post-level row on the same alert names no comment — it must
        // survive a comment delete (the alert sweep owns it).
        $owner->notify(new LikePostNotification($other, $alert, 'alert'));
        $this->assertSame(4, $this->orphanRows());

        // Deleting via the model fires only the parent's event; the FK
        // cascade drops the child row without events, so the sweep must catch
        // the child's rows too (same subtree subtlety as #57).
        $parent->delete();

        $this->assertSame(1, $this->orphanRows());
        $row = DB::table('notifications')->sole();
        $this->assertSame(LikePostNotification::class, $row->type);
    }

    public function test_sweep_matches_ids_exactly_not_by_prefix(): void
    {
        // Comment 11's payload contains `"comment_id":11,` — a naive
        // `...:1%` LIKE would prefix-match it. Ids forced apart
        // deterministically, mirroring #102's second test.
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $first = Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'a']);
        foreach (range(1, 9) as $i) {
            Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => "pad-{$i}"]);
        }
        $eleventh = Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'b']);
        $this->assertSame(1, $first->id);
        $this->assertSame(11, $eleventh->id);

        $owner->notify(new NewCommentOnPost($eleventh, $alert, 'alert'));
        $this->assertSame(1, $this->orphanRows());

        $first->delete();

        $this->assertSame(1, $this->orphanRows());
    }
}
