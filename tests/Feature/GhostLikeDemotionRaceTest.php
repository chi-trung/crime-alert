<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #279: #139's post-insert current read in LikeController::store()
 * checked only EXISTS — the existence half of the CommentController #153
 * idiom without its status half — while the approval gate ran outside the
 * transaction at the top of the method. An admin reject committing in the
 * gate-to-recheck window (an ordinary conditional UPDATE that clears
 * neither likes nor notifications) left the target row alive, so exists()
 * said yes: the like committed as a permanent ghost on hidden content
 * (#57's FK-less morph pair, unswept until the post is deleted outright),
 * the count inflated for the next pending->approved resurfacing, the author
 * got a bell for engagement moderation had just rejected — and the liker
 * could not even unlike, because destroy() runs the same gate. The fix
 * makes the locked current read status-aware exactly like #153: an
 * Alert/Experience must still be 'approved', and for a Comment its OWNING
 * post (never re-read in-transaction before #279) must be; a demoted
 * target erases the like, commits the erase, and answers the same 403 the
 * pre-race gate gave.
 */
class GhostLikeDemotionRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reject_committed_mid_like_leaves_no_ghost_row_or_bell(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // The gate (top of store) already passed on 'approved'. Inside the
        // insert, the rival's admin reject commits — deliberately RAW (no
        // model events, exactly the #189 conditional UPDATE reject() runs).
        Like::creating(function () use ($alert): void {
            DB::table('alerts')->where('id', $alert->id)->update(['status' => 'rejected']);
        });

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertForbidden();

        // Pre-fix both assertions fail: exists() cannot see a live-but-
        // rejected row, so the like commits and the bell fires.
        $this->assertSame(0, DB::table('likes')->where('likeable_type', Alert::class)->where('likeable_id', $alert->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }

    public function test_a_mid_flight_reject_of_the_owning_post_stales_a_comment_like(): void
    {
        // The type=comment leg: before #279 the comment's own existence was
        // the whole in-transaction check — a comment on a post rejected
        // mid-flight committed its like and belled the comment's author
        // about engagement on content moderation just hid.
        $postOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $postOwner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['user_id' => $commenter->id, 'alert_id' => $alert->id, 'content' => 'c']);

        Like::creating(function () use ($alert): void {
            DB::table('alerts')->where('id', $alert->id)->update(['status' => 'rejected']);
        });

        $this->actingAs($liker)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])
            ->assertForbidden();

        $this->assertSame(0, DB::table('likes')->where('likeable_type', Comment::class)->where('likeable_id', $comment->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $commenter->id)->count());
    }

    public function test_a_like_while_the_target_stays_approved_still_records_and_notifies(): void
    {
        // The guard must not become an unconditional 403: with the target
        // approved through the whole window, the like records, counts, and
        // the author is belled — the pre-#279 happy path, intact.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1]);

        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }
}
