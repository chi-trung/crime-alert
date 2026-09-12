<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_unread_notifications(): void
    {
        $this->getJson('/notifications/unread')->assertUnauthorized();
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
