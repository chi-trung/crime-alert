<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #53: row deletions left the uploaded images behind. AlertController
 * carefully replaced/deleted files in update(), but destroy() — on both the
 * user and admin routes — only removed the row, and account deletion (which
 * cascades at the DB level after #48) never fired model events at all.
 */
class UploadCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function alertWithImage(User $owner): Alert
    {
        $this->actingAs($owner)->post('/alerts', [
            'title' => 'Co hinh',
            'description' => 'd',
            'confirmCheckbox' => '1',
            'image' => UploadedFile::fake()->image('bang.png'),
        ]);

        $alert = Alert::firstOrFail();
        // store() moves the upload with a raw move() call rather than the
        // Storage facade, so the fake disk never sees the write itself.
        // Mirror the row's path into the fake so deletion cleanup is the
        // only thing under test.
        Storage::disk('public')->put($alert->image, 'fake-bytes');

        return $alert;
    }

    public function test_owner_deleting_an_alert_removes_its_image_file(): void
    {
        $owner = User::factory()->create();
        $alert = $this->alertWithImage($owner);
        $this->assertTrue(Storage::disk('public')->exists($alert->image));

        $this->actingAs($owner)->delete("/alerts/{$alert->id}")->assertRedirect();

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
        Storage::disk('public')->assertMissing($alert->image);
    }

    public function test_admin_deleting_someone_elses_alert_removes_the_file_too(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $alert = $this->alertWithImage($owner);

        $this->actingAs($admin)->delete("/admin/alerts/{$alert->id}")->assertRedirect();

        Storage::disk('public')->assertMissing($alert->image);
    }

    public function test_alert_without_image_deletes_cleanly(): void
    {
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Khong hinh', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($owner)->delete("/alerts/{$alert->id}")->assertRedirect();
        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
    }

    public function test_experience_delete_removes_avatar_and_account_deletion_removes_all_files(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/experiences', [
            'title' => 'Chia se',
            'content' => 'c',
            'name' => 'U',
            'avatar' => UploadedFile::fake()->image('face.png'),
        ]);
        $experience = Experience::firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($experience->avatar));

        $this->actingAs($user)->delete("/experiences/{$experience->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($experience->avatar);

        // The harder case: account deletion cascades rows at the DB level,
        // so only the explicit Eloquent pass in ProfileController can fire
        // the model events that clean the files.
        $alert = $this->alertWithImage($user);
        $this->actingAs($user)->post('/experiences', [
            'title' => 'Cua user', 'content' => 'c', 'name' => 'U',
            'avatar' => UploadedFile::fake()->image('face2.png'),
        ]);
        $experience2 = Experience::latest('id')->firstOrFail();

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
        $this->assertDatabaseMissing('experiences', ['id' => $experience2->id]);
        Storage::disk('public')->assertMissing($alert->image);
        Storage::disk('public')->assertMissing($experience2->avatar);
    }

    // Guard the pre-existing update() cleanup against the new event —
    // replacing uploads must not double-delete or crash.
    public function test_replacing_an_alert_image_still_removes_the_old_file(): void
    {
        $owner = User::factory()->create();
        $alert = $this->alertWithImage($owner);
        $old = $alert->image;

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Co hinh',
            'description' => 'd',
            'image' => UploadedFile::fake()->image('moi.png'),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($old);
        $fresh = $alert->fresh();
        $this->assertNotSame($old, $fresh->image);
        // (Raw move() again — mirror the replacement for the final check.)
        Storage::disk('public')->put($fresh->image, 'fake-bytes');

        // And deleting afterwards removes the replacement cleanly.
        $this->actingAs($owner)->delete("/alerts/{$alert->id}");
        Storage::disk('public')->assertMissing($fresh->image);
    }
}
