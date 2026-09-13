<?php

namespace Tests\Feature;

use App\Models\Alert;
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
}
