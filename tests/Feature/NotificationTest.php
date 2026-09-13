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
}
