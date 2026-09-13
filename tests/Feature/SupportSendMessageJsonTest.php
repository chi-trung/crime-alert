<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #206: the /support/{id} chat form POSTs via fetch() with
 * Accept: application/json, but sendMessage()'s rejection branches —
 * the #129 unverified gate, the open-status gate, and the #163 raced-close
 * transaction outcome — answered them all with back()->with('error'), a
 * 302. fetch() follows the redirect transparently: the followed GET of the
 * thread renders 200, so res.ok was TRUE on every rejection. The client
 * handler then cleared the textarea exactly as on success — the draft was
 * silently eaten and the flashed HTML never surfaced (the JSON response
 * body was never even parsed). The fix gives every terminal branch an
 * expectsJson() arm answering a real status code: 403 unverified, 409
 * closed (snapshot gate and raced close alike), 404 vanished, 200
 * {success:true} — while the plain form-POST shape keeps its flashes for
 * non-JSON callers.
 */
class SupportSendMessageJsonTest extends TestCase
{
    use RefreshDatabase;

    private array $json = ['Accept' => 'application/json'];

    private function openThread(User $owner): SupportRequest
    {
        return SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);
    }

    public function test_unverified_json_reply_is_a_403_json_error_not_a_followed_redirect(): void
    {
        $unverified = User::factory()->unverified()->create();
        $thread = $this->openThread($unverified);

        $this->actingAs($unverified)
            ->postJson(route('support.sendMessage', $thread), ['message' => 'hi'], $this->json)
            ->assertForbidden()
            ->assertJson(['success' => false, 'message' => 'Bạn cần xác thực email để liên hệ hỗ trợ.']);

        $this->assertSame(0, SupportMessage::count());
    }

    public function test_closed_thread_json_reply_is_a_409_carrying_the_gate_message(): void
    {
        $owner = User::factory()->create();
        $thread = $this->openThread($owner);
        $thread->update(['status' => 'closed']);

        $this->actingAs($owner)
            ->postJson(route('support.sendMessage', $thread), ['message' => 'hi'], $this->json)
            ->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.']);

        $this->assertSame(0, SupportMessage::count());
    }

    public function test_raced_close_json_reply_is_a_409_matching_the_snapshot_gate(): void
    {
        // The #163 transaction backs out mid-flight when an admin closes the
        // thread inside the gate-to-insert window. Pre-fix that outcome
        // reached the SAME 302-flash as the snapshot gate (so the JSON
        // client counted it as a successful send); post-fix the two arms are
        // indistinguishable to the client: same 409, same message.
        $owner = User::factory()->create();
        $thread = $this->openThread($owner);

        SupportMessage::creating(function () use ($thread) {
            DB::table('support_requests')->where('id', $thread->id)->update(['status' => 'closed']);
        });

        $response = $this->actingAs($owner)
            ->postJson(route('support.sendMessage', $thread), ['message' => 'late'], $this->json)
            ->assertStatus(409)
            ->assertJson(['success' => false, 'message' => 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.']);

        // The body is JSON, not the followed-redirect HTML page.
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(0, SupportMessage::count());
    }

    public function test_vanished_thread_json_reply_is_a_404_json_error(): void
    {
        $owner = User::factory()->create();
        $thread = $this->openThread($owner);

        SupportMessage::creating(function () use ($thread) {
            DB::table('support_requests')->where('id', $thread->id)->delete();
        });

        $this->actingAs($owner)
            ->postJson(route('support.sendMessage', $thread), ['message' => 'ghost'], $this->json)
            ->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Yêu cầu không tồn tại.']);
    }

    public function test_successful_json_send_answers_success_true(): void
    {
        $owner = User::factory()->create();
        User::factory()->admin()->create();
        $thread = $this->openThread($owner);

        $this->actingAs($owner)
            ->postJson(route('support.sendMessage', $thread), ['message' => 'hello'], $this->json)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(1, SupportMessage::count());
    }

    public function test_plain_form_post_rejections_keep_their_error_flashes(): void
    {
        // Control for both gates: the non-JSON contract (the fallback when
        // JS is off) must not turn into JSON errors.
        $owner = User::factory()->create();
        $closed = $this->openThread($owner);
        $closed->update(['status' => 'closed']);

        $this->actingAs($owner)
            ->from(route('support.show', $closed))
            ->post(route('support.sendMessage', $closed), ['message' => 'hi'])
            ->assertRedirect(route('support.show', $closed))
            ->assertSessionHas('error', 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.');

        $unverified = User::factory()->unverified()->create();
        $open = $this->openThread($unverified);

        $this->actingAs($unverified)
            ->from(route('support.show', $open))
            ->post(route('support.sendMessage', $open), ['message' => 'hi'])
            ->assertRedirect(route('support.show', $open))
            ->assertSessionHas('error', 'Bạn cần xác thực email để liên hệ hỗ trợ.');

        $this->assertSame(0, SupportMessage::count());
    }
}
