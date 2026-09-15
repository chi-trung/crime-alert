<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #141: comments.store and support.sendMessage were the only
 * user-facing write endpoints without a rate limit — a scripted flood
 * bell-storms post authors (NewCommentOnPost/NewReplyOnComment) and the
 * admin inbox (NewSupportMessage) indefinitely. Both routes now carry
 * inline throttle:30,1, the same shape #33 established on the chatbot
 * route; ThrottleRequests keys by authenticated user id inside the auth
 * group, so hitting the ceiling must 429 the spammer only.
 */
class WriteThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_store_is_throttled_at_thirty_requests_per_minute(): void
    {
        $author = User::factory()->create();
        $spammer = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // Route middleware is throttle:30,1 — the handler ends in back(),
        // so each accepted request is a 302; the 31st must never reach it.
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($spammer)
                ->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'c'.$i])
                ->assertStatus(302);
        }

        $this->actingAs($spammer)
            ->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'overflow'])
            ->assertStatus(429);

        $this->assertSame(30, $alert->comments()->count());
    }

    public function test_support_reply_is_throttled_at_thirty_messages_per_minute(): void
    {
        $owner = User::factory()->create();
        User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Câu hỏi']);

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($owner)
                ->post(route('support.sendMessage', $thread), ['message' => 'm'.$i])
                ->assertStatus(302);
        }

        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => 'overflow'])
            ->assertStatus(429);

        $this->assertSame(30, $thread->messages()->count());
    }

    public function test_throttle_is_per_user_not_per_endpoint(): void
    {
        // Control for the #33 rationale: the limiter keys by user id, so a
        // user who has exhausted their own window must not lock others out
        // of the same shared endpoint.
        $author = User::factory()->create();
        $spammer = User::factory()->create();
        $bystander = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        for ($i = 1; $i <= 31; $i++) {
            $this->actingAs($spammer)
                ->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'flood'.$i]);
        }

        $this->actingAs($bystander)
            ->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'innocent'])
            ->assertStatus(302);

        // 30 flood comments landed, the 31st was 429'd, plus the innocent one.
        $this->assertSame(31, $alert->comments()->count());
    }

    /**
     * Issue #297 (RL-1): #141 laned comments.store but left its two sibling
     * user writes — comments.update and comments.destroy — bare. destroy() is
     * the expensive one: Comment::deleting expands the whole reply subtree
     * (subtreeIds has no cap, and store() accepts a parent_id for ANY
     * comment, so an attacker grows the subtree unboundedly at the legitimate
     * 30/min insert lane) and issues ONE notifications DELETE carrying three
     * unindexable `data LIKE` predicates per subtree id against a TEXT column
     * with no index. Cost is attacker-sized by construction, spent in a
     * single request with zero 429 tier. update() is the same doctrine gap —
     * every other auth'd write in routes/web.php carries a named bucket.
     */
    public function test_comment_update_is_throttled_at_thirty_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => User::factory()->create()->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['user_id' => $user->id, 'alert_id' => $alert->id, 'content' => 'c']);

        // Both success paths of update() end in a 302 redirect, so each
        // accepted request is a 302 and the 31st must never reach it.
        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)
                ->put(route('comments.update', $comment), ['content' => 'edit'.$i])
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->put(route('comments.update', $comment), ['content' => 'overflow'])
            ->assertStatus(429);

        $this->assertSame('edit30', $comment->fresh()->content);
    }

    public function test_comment_destroy_is_throttled_at_thirty_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => User::factory()->create()->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        // Each DELETE needs its own row: the binding 404s on an already
        // deleted comment, but ThrottleRequests counts the ATTEMPT regardless
        // — and a laned route answers 429 from the 31st attempt even when the
        // first one already deleted its subject (the #180 login-test idiom).
        $comments = collect();
        for ($i = 1; $i <= 31; $i++) {
            $comments->push(Comment::create(['user_id' => $user->id, 'alert_id' => $alert->id, 'content' => 'c'.$i]));
        }

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)
                ->delete(route('comments.destroy', $comments[$i]))
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->delete(route('comments.destroy', $comments[30]))
            ->assertStatus(429);

        // The 31st never reached the controller: its comment survives.
        $this->assertDatabaseHas('comments', ['id' => $comments[30]->id]);
    }

    public function test_comment_update_and_destroy_do_not_share_counters(): void
    {
        // #147's bucket-prefix doctrine: bare (or same-prefix) limiters key
        // by user id alone, so grinding update would starve destroy — and
        // both would starve the store lane. Three named lanes, three budgets.
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => User::factory()->create()->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['user_id' => $user->id, 'alert_id' => $alert->id, 'content' => 'c']);
        $victim = Comment::create(['user_id' => $user->id, 'alert_id' => $alert->id, 'content' => 'v']);

        for ($i = 1; $i <= 30; $i++) {
            $this->actingAs($user)->put(route('comments.update', $comment), ['content' => 'e'.$i]);
        }
        $this->actingAs($user)->put(route('comments.update', $comment), ['content' => 'x'])
            ->assertStatus(429);

        // Update lane exhausted; destroy and store lanes must still answer.
        $this->actingAs($user)->delete(route('comments.destroy', $victim))
            ->assertStatus(302);
        $this->actingAs($user)->post(route('comments.store'), ['alert_id' => $alert->id, 'content' => 'still-ok'])
            ->assertStatus(302);
    }

    public function test_comment_write_throttles_are_per_user(): void
    {
        $owner = User::factory()->create();
        $bystander = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $mine = Comment::create(['user_id' => $owner->id, 'alert_id' => $alert->id, 'content' => 'm']);
        $theirs = Comment::create(['user_id' => $bystander->id, 'alert_id' => $alert->id, 'content' => 't']);

        for ($i = 1; $i <= 31; $i++) {
            $this->actingAs($owner)->put(route('comments.update', $mine), ['content' => 'e'.$i]);
        }
        $this->actingAs($owner)->put(route('comments.update', $mine), ['content' => 'x'])
            ->assertStatus(429);

        // The limiter keys by authenticated user id — the bystander keeps a
        // full budget on the same shared endpoints.
        $this->actingAs($bystander)->put(route('comments.update', $theirs), ['content' => 'fine'])
            ->assertStatus(302);
        $this->actingAs($bystander)->delete(route('comments.destroy', $theirs))
            ->assertStatus(302);
    }
}
