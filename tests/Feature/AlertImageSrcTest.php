<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #137: five <img> tags shipped literal \" around the src (a
 * paste-from-escaped-source artifact HTML parses into the value itself,
 * breaking every image), and the edit preview pointed at /alerts/x.jpg
 * instead of the storage/ URL. Each render site is pinned: the correct
 * absolute URL present, and no `src=\"` sequence anywhere on the page.
 */
class AlertImageSrcTest extends TestCase
{
    use RefreshDatabase;

    private function alertWithImage(User $user): Alert
    {
        return Alert::create([
            'user_id' => $user->id,
            'title' => 'Anh canh bao',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/pic-137.jpg',
        ]);
    }

    public function test_alert_pages_render_the_image_src_as_a_valid_attribute(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $alert = $this->alertWithImage($user);
        $url = 'storage/alerts/pic-137.jpg';

        $show = $this->actingAs($user)->get("/alerts/{$alert->id}")->assertOk()->getContent();
        $this->assertStringContainsString('src="http://localhost/'.$url.'"', $show);
        $this->assertStringNotContainsString('src=\\"', $show);

        $index = $this->actingAs($user)->get('/alerts')->assertOk()->getContent();
        $this->assertStringContainsString('src="http://localhost/'.$url.'"', $index);
        $this->assertStringNotContainsString('src=\\"', $index);
    }

    public function test_dashboard_image_thumbs_render_validly_for_owner_and_admin(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->alertWithImage($user);

        // Lines 433/470: the owner's own-latest card (user dashboard).
        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('src="http://localhost/storage/alerts/pic-137.jpg"', $html);
        $this->assertStringNotContainsString('src=\\"', $html);

        // Line 709: the admin dashboard's latest-community card. Same alert,
        // same image, different code path.
        $admin = User::factory()->admin()->create();
        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('src="http://localhost/storage/alerts/pic-137.jpg"', $html);
        $this->assertStringNotContainsString('src=\\"', $html);
    }

    public function test_edit_preview_points_at_the_storage_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $alert = $this->alertWithImage($user);

        $html = $this->actingAs($user)->get("/alerts/{$alert->id}/edit")->assertOk()->getContent();

        // asset() output for the public-disk path — not the old root-relative
        // /alerts/... guess that pointed nowhere.
        $this->assertStringContainsString('src="http://localhost/storage/alerts/pic-137.jpg"', $html);
        $this->assertStringNotContainsString('src="/alerts/', $html);
    }
}
