<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedUnread(User $user, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\NewPostNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['message' => "unread {$i}"]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_guest_cannot_fetch_unread_notifications(): void
    {
        $this->getJson('/notifications/unread')->assertUnauthorized();
    }

    public function test_unread_endpoint_reports_the_true_total_beyond_the_list_cap(): void
    {
        // Issue #69: count used to be taken from the take(10) dropdown
        // preview, so the badge froze at "10". The list may stay capped; the
        // number must not be.
        $user = User::factory()->create();
        $this->seedUnread($user, 15);

        $this->actingAs($user)->getJson('/notifications/unread')
            ->assertOk()
            ->assertJson(['count' => 15])
            ->assertJsonCount(10, 'notifications');
    }

    public function test_server_rendered_badge_shows_the_true_total(): void
    {
        $user = User::factory()->create();
        $this->seedUnread($user, 15);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('notification-badge" style="">15<', $html);
    }

    public function test_dropdown_renderer_does_not_interpolate_message_into_innerhtml(): void
    {
        // Regression for issue #18: noti.message embeds the notifier's name and
        // the post title (both user input). Rendering it via an innerHTML
        // template literal was a stored-XSS sink. The nav dropdown must build
        // rows with textContent instead.
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('${noti.message}', $html);
        $this->assertStringNotContainsString('${noti.created_at}', $html);
        $this->assertStringContainsString('content.textContent = noti.message', $html);
        $this->assertStringContainsString('time.textContent = noti.created_at', $html);
    }

    public function test_read_redirects_to_internal_url(): void
    {
        // Positive control for #110: a same-app url still redirects there.
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\NewPostNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'm', 'url' => '/alerts/1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('notifications.read', $id))
            ->assertRedirect('/alerts/1');

        $this->assertNotNull(DB::table('notifications')->where('id', $id)->whereNotNull('read_at')->first());
    }

    public function test_read_does_not_redirect_to_external_url(): void
    {
        // Issue #110: read() redirected to data['url'] unvalidated — any row
        // with an external url turned a bell click into an open redirect.
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\NewPostNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'm', 'url' => 'https://evil.example/phish']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('notifications.read', $id))
            ->assertRedirect(route('notifications.index'));

        // The read itself still lands — only the redirect target is tamed.
        $this->assertNotNull(DB::table('notifications')->where('id', $id)->whereNotNull('read_at')->first());
    }

    public function test_read_of_another_users_notification_is_404(): void
    {
        // Scope guard this fix must not loosen: ids are scoped to the actor.
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\NewPostNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'data' => json_encode(['message' => 'm', 'url' => '/alerts/1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($stranger)->get(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertNull(DB::table('notifications')->where('id', $id)->whereNotNull('read_at')->first());
    }

    public function test_unread_feed_links_through_the_read_route_not_the_raw_url(): void
    {
        // Issue #113: unreadAjax() returned the raw data['url'] and the
        // dropdown assigned it straight to a.href — clicks bypassed the
        // #110 isLocalUrl gate entirely (plus a javascript: href vector).
        // The feed now carries a server-built read_url; the raw url never
        // leaves the server.
        $user = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\NewPostNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'm', 'url' => 'https://evil.example/phish']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/notifications/unread')->assertOk();

        $response->assertJsonPath('notifications.0.read_url', route('notifications.read', $id));
        $this->assertStringNotContainsString('evil.example', $response->getContent());
    }

    public function test_dropdown_clicks_go_through_the_read_route(): void
    {
        // The rendered dropdown must point rows at the read route (which
        // marks read and validates the target, #110), never at raw urls.
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('a.href = noti.url', $html);
        $this->assertStringContainsString('noti.read_url', $html);
    }

    /**
     * Seed notifications with controlled uuid ids: $read rows take 'f…'
     * ids (sort FIRST under orderByDesc('id')), $unread take '0…' ids (sort
     * LAST). With 20 read + 5 unread, paginate(20)'s page-1 window is
     * entirely read rows and every unread sits on page 2 — the exact shape
     * that hid the read-all button before #193.
     */
    private function seedSplit(User $user, int $read, int $unread): void
    {
        for ($i = 0; $i < $read; $i++) {
            DB::table('notifications')->insert([
                'id' => sprintf('f%031d', $i),
                'type' => 'App\\Notifications\\NewPostNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['message' => 'read']),
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        for ($i = 0; $i < $unread; $i++) {
            DB::table('notifications')->insert([
                'id' => sprintf('0%031d', $i),
                'type' => 'App\\Notifications\\NewPostNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['message' => 'unread']),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_read_all_button_shows_when_unreads_live_on_a_later_page(): void
    {
        // Issue #193: the old gate read $notifications, the current page
        // slice. With 20 read on page 1 and 5 unread pushed to page 2, the
        // page-1 count of unread-in-slice is 0, so the button disappeared
        // even though readAll() still had real work — stranding the older
        // unreads behind per-row clicks. The fix gates on the global unread
        // exists(); readAll() itself was never page-scoped (#93).
        $user = User::factory()->create();
        $this->seedSplit($user, 20, 5);

        $html = $this->actingAs($user)->get('/notifications')->assertOk()->getContent();

        $this->assertStringContainsString('Đánh dấu tất cả đã đọc', $html);

        // And clicking it clears the whole backlog, not just page 1.
        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_read_all_button_hidden_only_when_nothing_is_unread(): void
    {
        // Control: the button still disappears when the account genuinely
        // has no unreads — the gate is global-unread, not unconditional.
        $user = User::factory()->create();
        $this->seedSplit($user, 5, 0);

        $html = $this->actingAs($user)->get('/notifications')->assertOk()->getContent();

        $this->assertStringNotContainsString('Đánh dấu tất cả đã đọc', $html);
    }

    public function test_support_request_notification_renders_a_real_message(): void
    {
        // Issue #192: NewSupportRequest::toArray shipped without a 'message'
        // key, so both renderers (the dropdown feed here and the
        // notifications page) fell back to 'Bạn có thông báo mới' — an admin
        // could not tell WHO opened a thread or ABOUT WHAT without clicking
        // each one. End-to-end through POST /support so the store() fan-out
        // itself is what's pinned, not just the class in isolation.
        $sender = User::factory()->create(['name' => 'Nguyễn Dân']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($sender)->post('/support', [
            'subject' => 'Tài khoản bị khóa nhầm',
            'message' => 'Tôi không đăng nhập được từ hôm qua.',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->getJson('/notifications/unread')->assertOk();

        $message = $response->json('notifications.0.message');
        $this->assertSame(
            'Người dùng Nguyễn Dân đã mở yêu cầu hỗ trợ: Tài khoản bị khóa nhầm',
            $message
        );
        // The generic fallback would render identically for every row —
        // asserting it is absent is the actual red-before-fix signal.
        $this->assertStringNotContainsString('Bạn có thông báo mới', $response->getContent());

        // The page renderer reads the same key.
        $this->actingAs($admin)->get('/notifications')
            ->assertOk()
            ->assertSee('Người dùng Nguyễn Dân đã mở yêu cầu hỗ trợ: Tài khoản bị khóa nhầm', false);
    }
}
