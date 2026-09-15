<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Tests\TestCase;

/**
 * Issue #281: UploadedFile::store()'s documented contract is 'string|false'
 * — FilesystemAdapter::put() catches UnableToWriteFile and, with the
 * 'throw' => false config both local and public disks here carry
 * (config/filesystems.php:37,46), putFileAs() returns false — so a full,
 * read-only, or permission-denied production disk fails EVERY upload this
 * way, quietly. All three call sites assigned the result straight into
 * $data, and PHP casts false to '0' into the VARCHAR: store() persisted a
 * pending alert with image='0' under a success flash and an admin bell for
 * an image nobody can open; update()'s guarded write landed title +
 * image='0' too — then #265's staleness re-read compared '0' against
 * (string)false ('') and answered "your changes were not saved" for an edit
 * that HAD been saved, while the clobbered column orphaned the previous
 * image file for good (destroy() later unlinks '0', which matches nothing).
 * The fix checks `=== false` at each store call site and returns a form
 * error before $data is touched: no row, no bell, no flash, and the old
 * image's column value and file both survive untouched.
 */
class UploadFalseReturnTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Swaps the 'public' disk for a custom driver whose putFileAs() returns
     * false — the exact documented throw=false failure contract — without
     * touching any other disk. Storage::fake('public') cannot model this:
     * its local writes always succeed.
     */
    private function bindFailingPublicDisk(): void
    {
        config(['filesystems.disks.public.driver' => 'failing-upload']);
        Storage::extend('failing-upload', function () {
            $local = new LocalFilesystemAdapter(sys_get_temp_dir());

            return new class(new Filesystem($local), $local, ['throw' => false, 'root' => sys_get_temp_dir()]) extends FilesystemAdapter
            {
                // Exactly what the real put() chain produces for a failed
                // write with 'throw' => false: putFileAs():~L491's
                // `return $result ? $path : false`.
                public function putFileAs($path, $file, $name = null, $options = [])
                {
                    return false;
                }
            };
        });
    }

    public function test_alert_store_with_a_failing_disk_persists_nothing(): void
    {
        $this->bindFailingPublicDisk();
        $admin = User::factory()->create(['isAdmin' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/alerts', [
            'title' => 'A',
            'description' => 'd',
            'confirmCheckbox' => 'yes',
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertSessionHasErrors('image');

        // Pre-fix: alert_count=1 with image_raw='0', 1 admin bell, success
        // flash — a pending post about an image that never existed.
        $this->assertSame(0, DB::table('alerts')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_alert_update_with_a_failing_disk_keeps_the_existing_image_untouched(): void
    {
        $this->bindFailingPublicDisk();
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'A',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/old.png',
        ]);

        $this->actingAs($user)->put('/alerts/'.$alert->id, [
            'title' => 'A2',
            'description' => 'd',
            'confirmCheckbox' => 'yes',
            'image' => UploadedFile::fake()->image('new.png'),
        ])->assertSessionHasErrors('image');

        // Pre-fix: the guarded write lands BOTH title='A2' and image='0',
        // the #265 re-read then reads false-'stale' (string '0' vs ''), so
        // the response claims the edit was lost while the column was really
        // clobbered — the old file orphaned, the row unrepairable (its next
        // edit reads $oldImage='0'). Post-fix the request dies at the store
        // call site: nothing written, no false 'stale' flash either.
        $live = DB::table('alerts')->where('id', $alert->id)->first();
        $this->assertSame('alerts/old.png', $live->image);
        $this->assertSame('A', $live->title);
        $this->assertSame('approved', $live->status);
        $this->assertArrayNotHasKey('info', session()->all());
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_experience_store_with_a_failing_disk_persists_nothing(): void
    {
        $this->bindFailingPublicDisk();
        $user = User::factory()->create();

        // The experiences form has no avatar input, so this leg is reached
        // by a crafted multipart POST — still an auth'd, validated route.
        $this->actingAs($user)->post('/experiences', [
            'title' => 'E',
            'content' => 'c',
            'name' => 'N',
            'avatar' => UploadedFile::fake()->image('a.png'),
        ])->assertSessionHasErrors('avatar');

        $this->assertSame(0, DB::table('experiences')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_a_working_disk_still_stores_and_records_the_alert_image(): void
    {
        // The false check must not eat the happy path: a real (fake-bound)
        // public disk still writes the file, still records its path, still
        // flashes success and bells admins.
        Storage::fake('public');
        $admin = User::factory()->create(['isAdmin' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/alerts', [
            'title' => 'A',
            'description' => 'd',
            'confirmCheckbox' => 'yes',
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertSessionHas('success');

        $image = DB::table('alerts')->value('image');
        $this->assertIsString($image);
        $this->assertStringStartsWith('alerts/', $image);
        $this->assertTrue(Storage::disk('public')->exists($image));
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $admin->id)->count());
    }

    public function test_an_update_without_an_upload_still_saves_and_keeps_the_image(): void
    {
        // The keep-branch (no uploaded file at all) must never be caught by
        // the new check: it bypasses store() entirely.
        Storage::fake('public');
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'A',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/old.png',
        ]);

        $this->actingAs($user)->put('/alerts/'.$alert->id, [
            'title' => 'A2',
            'description' => 'd',
            'confirmCheckbox' => 'yes',
        ])->assertSessionHas('success');

        $live = DB::table('alerts')->where('id', $alert->id)->first();
        $this->assertSame('A2', $live->title);
        $this->assertSame('alerts/old.png', $live->image);
    }
}
