<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #57: `likes` is a morph relation, so no FK can cascade it — every
 * delete path (post, comment thread, account removal) left the like rows
 * of the removed content behind.
 */
class LikeOrphanTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_alert_removes_its_likes_and_comment_likes(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $liker->id, 'content' => 'c']);
        // A reply inherits alert_id in CommentController::store, so mimic that.
        $reply = Comment::create(['alert_id' => $alert->id, 'parent_id' => $comment->id, 'user_id' => $owner->id, 'content' => 'r']);
        $alert->likes()->create(['user_id' => $liker->id]);
        $comment->likes()->create(['user_id' => $owner->id]);
        $reply->likes()->create(['user_id' => $liker->id]);

        $this->actingAs($owner)->delete("/alerts/{$alert->id}")->assertRedirect();

        $this->assertDatabaseMissing('likes', ['likeable_type' => Alert::class, 'likeable_id' => $alert->id]);
        $this->assertDatabaseMissing('likes', ['likeable_type' => Comment::class, 'likeable_id' => $comment->id]);
        $this->assertDatabaseMissing('likes', ['likeable_type' => Comment::class, 'likeable_id' => $reply->id]);
    }

    public function test_deleting_an_experience_removes_likes_on_its_thread(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $exp = Experience::create(['user_id' => $owner->id, 'title' => 'T', 'content' => 'c', 'name' => 'N', 'status' => 'approved']);
        $comment = Comment::create(['experience_id' => $exp->id, 'user_id' => $liker->id, 'content' => 'c']);
        $exp->likes()->create(['user_id' => $liker->id]);
        $comment->likes()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->delete("/experiences/{$exp->id}")->assertRedirect();

        $this->assertDatabaseMissing('likes', ['likeable_type' => Experience::class, 'likeable_id' => $exp->id]);
        $this->assertDatabaseMissing('likes', ['likeable_type' => Comment::class, 'likeable_id' => $comment->id]);
    }

    public function test_deleting_a_parent_comment_sweeps_the_reply_subtrees_likes(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $alert = Alert::create(['user_id' => $a->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $a->id, 'content' => 'p']);
        $child = Comment::create(['alert_id' => $alert->id, 'parent_id' => $parent->id, 'user_id' => $b->id, 'content' => 'c']);
        $grandchild = Comment::create(['alert_id' => $alert->id, 'parent_id' => $child->id, 'user_id' => $a->id, 'content' => 'g']);
        $parent->likes()->create(['user_id' => $b->id]);
        $child->likes()->create(['user_id' => $a->id]);
        $grandchild->likes()->create(['user_id' => $b->id]);

        // Deleting via the route fires only the parent's event; the FK
        // cascade drops child/grandchild rows without events, so the
        // subtree sweep must catch all three like rows.
        $this->actingAs($a)->delete("/comments/{$parent->id}")->assertRedirect();

        $this->assertSame(
            0,
            Like::where('likeable_type', Comment::class)
                ->whereIn('likeable_id', [$parent->id, $child->id, $grandchild->id])
                ->count()
        );
    }

    public function test_account_deletion_removes_likes_everywhere(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $user->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $exp = Experience::create(['user_id' => $user->id, 'title' => 'T', 'content' => 'c', 'name' => 'N', 'status' => 'approved']);
        $comment = Comment::create(['experience_id' => $exp->id, 'user_id' => $user->id, 'content' => 'c']);
        // Someone else liked the victim's content: those rows have the
        // other user's user_id, so the likes.user_id FK keeps them unless
        // the post-side sweeps in the model hooks run.
        $alert->likes()->create(['user_id' => $other->id]);
        $exp->likes()->create(['user_id' => $other->id]);
        $comment->likes()->create(['user_id' => $other->id]);

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseMissing('likes', ['likeable_type' => Alert::class, 'likeable_id' => $alert->id]);
        $this->assertDatabaseMissing('likes', ['likeable_type' => Experience::class, 'likeable_id' => $exp->id]);
        $this->assertDatabaseMissing('likes', ['likeable_type' => Comment::class, 'likeable_id' => $comment->id]);
        // The departing user's own likes are gone via the user_id FK.
        $this->assertDatabaseMissing('likes', ['user_id' => $user->id]);
    }
}
