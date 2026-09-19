<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #392: an alert with no image rendered a featureless grey box — the
 * thumb-ph placeholder — as the top of its card. The image branch carries
 * alt="Ảnh cảnh báo"; the placeholder branch carried nothing, so a
 * screen-reader user heard the card's title with no hint that the grey box
 * above it was standing in for a picture.
 *
 * news/index already names its placeholder with role="img" aria-label; this
 * makes the alert placeholders consistent with that.
 */
class ImagePlaceholderNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_alert_without_an_image_announces_its_placeholder(): void
    {
        $alert = Alert::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Cảnh báo thử nghiệm',
            'description' => 'Nội dung thử nghiệm.',
            'type' => 'Gian lận',
            'status' => 'approved',
            'latitude' => 10.76,
            'longitude' => 106.66,
        ]);

        $this->assertNull($alert->fresh()->image, 'precondition: the alert has no image');

        $html = $this->actingAs($alert->user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'role="img" aria-label="Ảnh cảnh báo"',
            $html,
            'the placeholder on the alert listing must announce itself as the alert picture'
        );
    }

    public function test_an_alert_with_an_image_does_not_render_a_placeholder(): void
    {
        $user = User::factory()->create();
        Alert::create([
            'user_id' => $user->id,
            'title' => 'Cảnh báo có ảnh',
            'description' => 'Nội dung thử nghiệm.',
            'type' => 'Gian lận',
            'status' => 'approved',
            'latitude' => 10.76,
            'longitude' => 106.66,
            'image' => 'alerts/test.jpg',
        ]);

        $html = $this->actingAs($user)
            ->get(route('alerts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('alt="Ảnh cảnh báo"', $html);
        $this->assertStringNotContainsString(
            'thumb-ph',
            $html,
            'the placeholder must not ship alongside the real image — pin the @if branch'
        );
    }

    public function test_the_dashboard_placeholders_are_named(): void
    {
        // The two thumb-ph placeholders are gated apart by the controller, not
        // just the view. $myLatest ships only from forUser(); $latestAlert
        // ships only from forAdmin(). So an admin sees one placeholder and a
        // plain user sees the other — never both, never neither.
        $admin = tap(User::factory()->create(['email_verified_at' => now()]), fn ($u) => $u->forceFill([
            'isAdmin' => true,
        ])->save());
        Alert::create([
            'user_id' => $admin->id,
            'title' => 'Cảnh báo mới nhất trên trang',
            'description' => 'Nội dung thử nghiệm.',
            'type' => 'Gian lận',
            'status' => 'approved',
            'latitude' => 10.76,
            'longitude' => 106.66,
        ]);

        $adminHtml = $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($adminHtml, 'role="img" aria-label="Ảnh cảnh báo"'),
            'the admin dashboard names the site-wide latest-alert placeholder'
        );

        $user = User::factory()->create(['email_verified_at' => now()]);
        Alert::create([
            'user_id' => $user->id,
            'title' => 'Bài đăng của tôi',
            'description' => 'Nội dung thử nghiệm.',
            'type' => 'Gian lận',
            'status' => 'approved',
            'latitude' => 10.76,
            'longitude' => 106.66,
        ]);

        $userHtml = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($userHtml, 'role="img" aria-label="Ảnh cảnh báo"'),
            'the user dashboard names the my-latest-alert placeholder'
        );
    }
}
