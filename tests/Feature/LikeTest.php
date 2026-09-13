<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\Like;
use App\Models\User;
use App\Notifications\LikePostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Issue #43: the like endpoints had zero coverage and leaky error paths —
 * destroy() wrapped everything in a catch-all that returned $e->getMessage(),
 * and store()'s check-then-insert could race onto the unique index.
 */
class LikeTest extends TestCase
{
    use RefreshDatabase;

    private function alert(User $owner): Alert
    {
        return Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'approved']);
    }

    public function test_like_increments_once_and_unlike_decrements(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = $this->alert($owner);

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 1]);
        // Second like by the same user: count unchanged.
        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['count' => 1]);
        $this->assertSame(1, Like::count());

        $this->actingAs($liker)->postJson('/like/unlike', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 0]);

        // Unliking twice is still a graceful success.
        $this->actingAs($liker)->postJson('/like/unlike', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['count' => 0]);
    }

    public function test_unlike_of_unknown_id_is_404_without_leaking_internals(): void
    {
        $liker = User::factory()->create();

        $response = $this->actingAs($liker)
            ->postJson('/like/unlike', ['type' => 'alert', 'id' => 99999])
            ->assertNotFound()
            ->assertJson(['success' => false]);

        // The old catch-all returned 500 with the raw exception message —
        // no SQL fragments or paths may appear anywhere in the body.
        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsStringIgnoringCase('exception', $body);
    }

    public function test_invalid_type_is_validation_error_not_500(): void
    {
        // Issue #43: validate() sat inside the try block, so bad input came
        // back as a 500 with a message body.
        $liker = User::factory()->create();

        $this->actingAs($liker)
            ->postJson('/like/unlike', ['type' => 'banana', 'id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_duplicate_like_race_resolves_without_500(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = $this->alert($owner);

        // Reproduce the lost race deterministically: store() has passed its
        // exists() check, and the competing row lands while the controller's
        // own insert is building. The QueryException from the unique index
        // must be caught, not returned as a 500.
        Like::creating(function ($like) {
            DB::table('likes')->insert([
                'user_id' => $like->user_id,
                'likeable_type' => $like->likeable_type,
                'likeable_id' => $like->likeable_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Notification::fake();
        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 1]);

        $this->assertSame(1, Like::count());
        // The race loser must not double-notify the author.
        Notification::assertNotSentTo($owner, LikePostNotification::class);
    }

    public function test_comment_like_counts_across_types(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $alert = $this->alert($owner);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $commenter->id, 'content' => 'c']);

        $this->actingAs($owner)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])
            ->assertOk()->assertJson(['count' => 1]);
        $this->assertSame(1, $comment->fresh()->likes()->count());
    }

    public function test_like_on_pending_alert_is_forbidden(): void
    {
        // Issue #104: store() resolved any existing id via bare findOrFail
        // (#43) and inserted — a verified user knowing a pending id could
        // bump its count and spam the author via LikePostNotification, the
        // same read-gated/write-open shape as #95 (gated in #96).
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertForbidden();

        $this->assertSame(0, Like::count());
        $this->assertSame(0, $owner->fresh()->notifications()->count());
    }

    public function test_like_on_rejected_experience_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'rejected',
        ]);

        $this->actingAs($liker)->postJson('/like', ['type' => 'experience', 'id' => $experience->id])
            ->assertForbidden();

        $this->assertSame(0, Like::count());
    }

    public function test_comment_like_on_pending_post_is_forbidden(): void
    {
        // Comments have no status of their own — the owning post governs,
        // mirroring the reply gate in #96.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'pending']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'c']);

        $this->actingAs($liker)->postJson('/like', ['type' => 'comment', 'id' => $comment->id])
            ->assertForbidden();

        $this->assertSame(0, Like::count());
        $this->assertSame(0, $owner->fresh()->notifications()->count());
    }

    public function test_unlike_on_unapproved_post_is_forbidden(): void
    {
        // Symmetric gate on destroy: an approved post liked, then pulled
        // back to pending by moderation, must not accept further
        // like-state writes from non-privileged users.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = $this->alert($owner);
        $alert->likes()->create(['user_id' => $liker->id]);
        $alert->update(['status' => 'pending']);

        $this->actingAs($liker)->postJson('/like/unlike', ['type' => 'alert', 'id' => $alert->id])
            ->assertForbidden();

        $this->assertSame(1, Like::count());
    }

    public function test_like_on_approved_post_still_succeeds(): void
    {
        // Positive control: the gate only touches unapproved targets.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $alert = $this->alert($owner);

        $this->actingAs($liker)->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 1]);

        $this->assertSame(1, Like::count());
    }

    public function test_like_button_hidden_on_pending_post(): void
    {
        // Issue #104: the server gate 403s like writes on unapproved posts,
        // so the blades hide the button like the comment form — an owner or
        // admin previewing a pending post must not get a dead button.
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($owner)->get(route('alerts.show', $alert))
            ->assertOk()->assertDontSee('like-btn-alert', false);
        $this->actingAs($admin)->get(route('alerts.show', $alert))
            ->assertOk()->assertDontSee('like-btn-alert', false);

        // The approved sibling still renders the button for the same viewer.
        $alert->update(['status' => 'approved']);
        $this->actingAs($owner)->get(route('alerts.show', $alert))
            ->assertOk()->assertSee('like-btn-alert', false);
    }

    public function test_experience_like_button_hidden_on_pending_post(): void
    {
        $owner = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'pending',
        ]);

        $this->actingAs($owner)->get(route('experiences.show', $experience))
            ->assertOk()->assertDontSee('like-btn-exp', false);

        $experience->update(['status' => 'approved']);
        $this->actingAs($owner)->get(route('experiences.show', $experience))
            ->assertOk()->assertSee('like-btn-exp', false);
    }
}
