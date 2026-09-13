<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #147: the like pair is a bell-flood primitive after all — #141
 * deferred it reasoning "likes are idempotent per user per target", which
 * holds for the row but not for the bell: store() notifies on every
 * *insert*, so alternating like/unlike cycles re-notify the author without
 * limit (probe on current main: 60 cycles = 60 LikePostNotification rows,
 * zero 429s). Both routes now share one throttle:60,1,like bucket (~30
 * full cycles/min), and every inline throttle in the app gains an explicit
 * lane prefix: a probe showed ThrottleRequests keys inline throttles by
 * user id ALONE, so #33's and #141's unprefixed limiters silently drained
 * one shared counter against each other.
 */
class LikeThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_unlike_cycle_is_capped_at_sixty_requests_per_minute(): void
    {
        $author = User::factory()->create();
        $spammer = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // 30 full cycles = 60 requests, all within the bucket.
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($spammer)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
                ->assertOk()->assertJson(['success' => true]);
            $this->actingAs($spammer)->postJson('/like/unlike', ['type' => 'alert', 'id' => $alert->id])
                ->assertOk()->assertJson(['success' => true]);
        }
        // Every cycle re-belled the author — the exact flood this issue is
        // about; after the fix the growth stops at the ceiling below.
        $this->assertSame(30, DB::table('notifications')->where('notifiable_id', $author->id)->count());

        // The 61st request (31st like) must 429 instead of landing a 31st bell.
        $this->actingAs($spammer)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertStatus(429);
        $this->assertSame(30, DB::table('notifications')->where('notifiable_id', $author->id)->count());
    }

    public function test_like_bucket_is_per_user(): void
    {
        $author = User::factory()->create();
        $spammer = User::factory()->create();
        $bystander = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // 30 full cycles = 60 requests = the whole bucket; then the
        // 61st 429s the spammer while a bystander is untouched.
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($spammer)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])->assertOk();
            $this->actingAs($spammer)->postJson('/like/unlike', ['type' => 'alert', 'id' => $alert->id])->assertOk();
        }
        $this->actingAs($spammer)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])->assertStatus(429);

        $this->actingAs($bystander)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 1]);
    }

    public function test_lanes_are_isolated_comment_bucket_does_not_drain_support(): void
    {
        // Pins the prefix part of the fix. Pre-#147 (bare throttle:30,1 on
        // both, keyed by user id alone) exhausting comments.store 429'd the
        // very first support.sendMessage of the same user; now each lane
        // carries its own counter.
        $author = User::factory()->create();
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $thread = SupportRequest::create(['user_id' => $user->id, 'subject' => 'S']);

        $this->actingAs($user);

        for ($i = 1; $i <= 30; $i++) {
            $this->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'c'.$i])->assertStatus(302);
        }
        $this->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'overflow'])->assertStatus(429);

        // The other lane is untouched by the comments exhaustion.
        $this->post(route('support.sendMessage', $thread), ['message' => 'hello'])->assertStatus(302);
    }

    public function test_comment_like_notifications_cap_with_the_same_bucket(): void
    {
        // Comment targets ride the same pair of routes, so the cycle bound
        // must apply to the LikeCommentNotification bell too.
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $spammer = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $commenter->id, 'content' => 'c']);

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($spammer)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])->assertOk();
            $this->actingAs($spammer)->postJson('/like/unlike', ['type' => 'comment', 'id' => $comment->id])->assertOk();
        }
        $this->assertSame(30, DB::table('notifications')->where('notifiable_id', $commenter->id)->count());
        $this->actingAs($spammer)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])->assertStatus(429);
    }
}
