<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    private function makeThread(): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $thread = SupportRequest::create([
            'user_id' => $owner->id,
            'subject' => 'Câu hỏi về cảnh báo',
        ]);

        return [$owner, $admin, $other, $thread];
    }

    public function test_guests_cannot_access_support(): void
    {
        [, , , $thread] = $this->makeThread();

        $this->get(route('support.show', $thread))->assertRedirect();
        $this->getJson(route('support.messages', ['supportRequest' => $thread]))->assertUnauthorized();
    }

    public function test_owner_and_admin_can_view_thread(): void
    {
        [$owner, $admin, , $thread] = $this->makeThread();

        $this->actingAs($owner)->get(route('support.show', $thread))->assertOk();
        $this->actingAs($admin)->get(route('support.show', $thread))->assertOk();
    }

    public function test_other_users_cannot_view_thread(): void
    {
        [, , $other, $thread] = $this->makeThread();

        $this->actingAs($other)->get(route('support.show', $thread))->assertForbidden();
    }

    public function test_other_users_cannot_read_messages_api(): void
    {
        [, , $other, $thread] = $this->makeThread();

        $this->actingAs($other)
            ->getJson(route('support.messages', ['supportRequest' => $thread]))
            ->assertForbidden();
    }

    public function test_other_users_cannot_post_into_thread(): void
    {
        [, , $other, $thread] = $this->makeThread();

        $this->actingAs($other)
            ->post(route('support.sendMessage', $thread), ['message' => 'xin chao'])
            ->assertForbidden();

        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_closed_thread_rejects_new_messages(): void
    {
        [$owner, , , $thread] = $this->makeThread();
        $thread->update(['status' => 'closed']);

        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => 'xin chao'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_message_content_is_escaped_in_server_rendered_view(): void
    {
        [$owner, , , $thread] = $this->makeThread();
        SupportMessage::create([
            'support_request_id' => $thread->id,
            'user_id' => $owner->id,
            'message' => '<script>alert("xss")</script>',
        ]);

        $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_admin_flag_exposed_for_client_rendering(): void
    {
        [$owner, $admin, , $thread] = $this->makeThread();
        SupportMessage::create([
            'support_request_id' => $thread->id,
            'user_id' => $admin->id,
            'message' => 'Chao ban',
        ]);

        $this->actingAs($owner)
            ->getJson(route('support.messages', ['supportRequest' => $thread]))
            ->assertOk()
            ->assertJsonPath('messages.0.is_admin', true)
            ->assertJsonPath('messages.0.is_me', false);
    }

    public function test_message_is_length_bounded_on_create_and_reply(): void
    {
        // Issue #39: support_messages.message is TEXT with no validation max,
        // the same overflow/storage-spam class closed for alerts/experiences
        // in #37.
        [$owner, , , $thread] = $this->makeThread();
        $huge = str_repeat('ạ', 5001);

        $this->actingAs($owner)->post('/support', [
            'subject' => 'dai', 'message' => $huge,
        ])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('support_requests', 1); // only makeThread's

        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => $huge])
            ->assertSessionHasErrors('message');
        $this->assertDatabaseCount('support_messages', 0);

        // Exactly at the bound is accepted (3-byte UTF-8 stays inside TEXT).
        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => str_repeat('ạ', 5000)])
            ->assertSessionHasNoErrors();
        $this->assertSame(5000, mb_strlen(SupportMessage::latest('id')->value('message')));
    }
}
