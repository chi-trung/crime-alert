<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #165: the three fan-out stores #141/#147 left bare — POST /support,
 * POST /alerts, POST /experiences each write one bell per admin per request
 * synchronously (none of the notification classes implements ShouldQueue),
 * so one verified account grows the admin inbox at HTTP speed (probe on
 * pre-fix main: 45 rapid POST /support = 45 threads, 45x-admin bells, zero
 * 429s). Each route now carries its own named lane (throttle:5,1,<lane>,
 * per #147's finding that inline throttles share one user-keyed counter);
 * 5/min caps the multiplier while staying generous against a human actually
 * composing a post.
 */
class CreateFanoutThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function bells(string $type): int
    {
        return DB::table('notifications')->where('type', $type)->count();
    }

    public function test_support_create_lane_caps_thread_and_bell_fan_out(): void
    {
        $user = User::factory()->create();
        User::factory()->admin()->create();

        // 5 submissions ride through: each lands thread + opening message
        // and one NewSupportRequest bell for the admin.
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)
                ->post('/support', ['subject' => 'S'.$i, 'message' => 'm'.$i])
                ->assertStatus(302);
        }
        $this->assertSame(5, SupportRequest::count());
        $this->assertSame(5, SupportMessage::count());
        $this->assertSame(5, $this->bells(NewSupportRequest::class));

        // The 6th 429s before the controller runs — no 6th row, no 6th bell.
        // Pre-fix this assertStatus(429) failed: the route had no limiter.
        $this->actingAs($user)
            ->post('/support', ['subject' => 'S6', 'message' => 'm6'])
            ->assertStatus(429);
        $this->assertSame(5, SupportRequest::count());
        $this->assertSame(5, $this->bells(NewSupportRequest::class));
    }

    public function test_alert_create_lane_caps_pending_approval_bells(): void
    {
        $user = User::factory()->create();
        User::factory()->admin()->create();

        // confirmCheckbox is an 'accepted' rule, so the passes must carry it
        // to exercise the fan-out rather than a validation redirect.
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)
                ->post('/alerts', ['title' => 'T'.$i, 'description' => 'd', 'confirmCheckbox' => 1])
                ->assertStatus(302);
        }
        $this->assertSame(5, Alert::count());
        $this->assertSame(5, $this->bells(NewPostPendingApprovalNotification::class));

        $this->actingAs($user)
            ->post('/alerts', ['title' => 'T6', 'description' => 'd', 'confirmCheckbox' => 1])
            ->assertStatus(429);
        $this->assertSame(5, Alert::count());
        $this->assertSame(5, $this->bells(NewPostPendingApprovalNotification::class));
    }

    public function test_experience_create_lane_caps_pending_approval_bells(): void
    {
        $user = User::factory()->create();
        User::factory()->admin()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)
                ->post('/experiences', ['title' => 'T'.$i, 'content' => 'c', 'name' => 'N'])
                ->assertStatus(302);
        }
        $this->assertSame(5, $this->bells(NewPostPendingApprovalNotification::class));

        $this->actingAs($user)
            ->post('/experiences', ['title' => 'T6', 'content' => 'c', 'name' => 'N'])
            ->assertStatus(429);
        $this->assertSame(5, $this->bells(NewPostPendingApprovalNotification::class));
    }

    public function test_create_lanes_are_isolated_from_each_other_and_from_support(): void
    {
        // Pins the named-lane part of the fix (each limiter needs its own
        // prefix — #147 showed bare throttle:N,1 throttles share a
        // user-keyed counter app-wide). Exhausting support-create must not
        // touch alert-create, exp-create, or the sendMessage lane.
        $user = User::factory()->create();
        User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $user->id, 'subject' => 'S']);

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->post('/support', ['subject' => 'S'.$i, 'message' => 'm'])->assertStatus(302);
        }
        $this->actingAs($user)->post('/support', ['subject' => 'overflow', 'message' => 'm'])->assertStatus(429);

        $this->actingAs($user)
            ->post('/alerts', ['title' => 'Untouched', 'description' => 'd', 'confirmCheckbox' => 1])
            ->assertStatus(302);
        $this->actingAs($user)
            ->post('/experiences', ['title' => 'Untouched', 'content' => 'c', 'name' => 'N'])
            ->assertStatus(302);
        $this->actingAs($user)
            ->post(route('support.sendMessage', $thread), ['message' => 'still fine'])
            ->assertStatus(302);
    }

    public function test_lanes_are_per_user(): void
    {
        $spammer = User::factory()->create();
        $bystander = User::factory()->create();
        User::factory()->admin()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($spammer)->post('/support', ['subject' => 'S', 'message' => 'm'])->assertStatus(302);
        }
        $this->actingAs($spammer)->post('/support', ['subject' => 'S', 'message' => 'm'])->assertStatus(429);

        $this->actingAs($bystander)->post('/support', ['subject' => 'B', 'message' => 'm'])->assertStatus(302);
        $this->assertSame(6, SupportRequest::count());
    }
}
