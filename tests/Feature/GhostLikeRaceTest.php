<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\Like;
use App\Models\User;
use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #139: store()'s check -> insert -> notify used to be three
 * autocommitted statements, so a like request that had passed exists() while
 * its target was deleted mid-flight landed a ghost row *after* the target's
 * deleting sweeps (the morph pair has no FK to cascade it, #57) plus a
 * notification bell pointing at a dead post (#121) — both surviving
 * forever. The fix wraps the insert path in a transaction with a
 * post-insert current-read re-check of the target. Reproduced with the
 * repo's own race idiom from LikeTest (#43): a Like::creating hook that
 * runs the whole delete inside the check-to-insert window.
 */
class GhostLikeRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_on_alert_deleted_mid_request_leaves_no_ghost_row_or_bell(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // The target's complete delete (sweeps + row) lands after the
        // controller's exists() check and before the insert returns.
        Like::creating(function () use ($alert) {
            $alert->delete();
        });

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertNotFound()
            ->assertJson(['success' => false]);

        // Pre-fix, both assertions below fail: the late insert survives the
        // already-finished sweep, and the notification fires after it.
        $this->assertSame(0, DB::table('likes')->where('likeable_type', Alert::class)->where('likeable_id', $alert->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_like_on_experience_deleted_mid_request_leaves_no_ghost(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $experience = Experience::create(['user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'approved']);

        Like::creating(function () use ($experience) {
            $experience->delete();
        });

        $this->actingAs($liker)->postJson('/like', ['type' => 'experience', 'id' => $experience->id])
            ->assertNotFound()
            ->assertJson(['success' => false]);

        $this->assertSame(0, DB::table('likes')->where('likeable_type', Experience::class)->where('likeable_id', $experience->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_like_on_comment_deleted_mid_request_leaves_no_ghost_and_no_comment_bell(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $commenter->id, 'content' => 'c']);

        Like::creating(function () use ($comment) {
            $comment->delete();
        });

        $this->actingAs($liker)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])
            ->assertNotFound()
            ->assertJson(['success' => false]);

        $this->assertSame(0, DB::table('likes')->where('likeable_type', Comment::class)->where('likeable_id', $comment->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $commenter->id)->where('type', LikeCommentNotification::class)->count());
    }

    public function test_vanished_target_on_a_plain_form_post_redirects_with_an_error(): void
    {
        // The non-JSON branch of the new 404 guard matches the endpoint's
        // redirect+error shape (the JS callers get the JSON branch above).
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        Like::creating(function () use ($alert) {
            $alert->delete();
        });

        $this->actingAs($liker)->post('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertSessionHas('error');

        $this->assertSame(0, DB::table('likes')->where('likeable_type', Alert::class)->where('likeable_id', $alert->id)->count());
    }

    public function test_normal_like_still_counts_and_notifies(): void
    {
        // Positive control: the re-check only fires on real target death —
        // a normal like on a living post inserts once and sends exactly
        // one LikePostNotification to the author.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1]);

        $this->assertSame(1, DB::table('likes')->where('likeable_type', Alert::class)->where('likeable_id', $alert->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $owner->id)->where('type', LikePostNotification::class)->count());
    }
}
