<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_closing_an_already_closed_thread_is_a_noop_with_info(): void
    {
        // Issue #98: close() rewrote the same status and flashed success on
        // repeat clicks. An already-closed thread must not be updated and
        // must report info, not success.
        [, $admin, , $thread] = $this->makeThread();
        $thread->update(['status' => 'closed']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)
            ->post(route('admin.support.close', $thread))
            ->assertSessionHas('info')
            ->assertSessionMissing('success');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $updates = array_filter($log, fn (array $q) => str_starts_with(strtolower($q['query']), 'update'));
        $this->assertCount(0, $updates, 'close on a closed thread issued an UPDATE');
        $this->assertSame('closed', $thread->fresh()->status);
    }

    public function test_closing_an_open_thread_still_flips_status_with_success(): void
    {
        // Positive control for #98: the live transition is untouched.
        [, $admin, , $thread] = $this->makeThread();

        $this->actingAs($admin)
            ->post(route('admin.support.close', $thread))
            ->assertSessionHas('success');

        $this->assertSame('closed', $thread->fresh()->status);
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

    public function test_dead_admin_id_column_is_gone(): void
    {
        // Issue #108: admin_id had no write site (store() sets user_id and
        // subject only) and no read site (the admin() relation had zero
        // callers) — the migration drops it, the model drops the relation
        // and the fillable entry. migrate:fresh in every test already proves
        // the migration runs on sqlite; mysql CI proves the other dialect.
        $this->assertFalse(Schema::hasColumn('support_requests', 'admin_id'));
        $this->assertFalse(method_exists(SupportRequest::class, 'admin'));

        // The thread lifecycle the column used to ride along with is intact.
        [$owner, $admin, , $thread] = $this->makeThread();
        $this->assertSame('open', $thread->fresh()->status);
        $this->actingAs($admin)
            ->post(route('admin.support.close', $thread))
            ->assertSessionHas('success');
        $this->assertSame('closed', $thread->fresh()->status);
    }
}
