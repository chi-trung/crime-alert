<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #267: AlertController::store() and ExperienceController::store()
 * wrote the upload to the public disk BEFORE a bare create(), with no
 * transaction and no sweep of the stored path when the insert failed.
 * update() had TWO dedicated fixes for exactly this shape (#233/#255's
 * doctrine: a disk write cannot roll back, so only the captured path can
 * free it) — store() had neither. When create() threw (deadlock against a
 * moderation sweep, connection drop, or the FK violation when #266's
 * transactional destroy() commits the user delete under this still-live
 * session), the request 500ed and the upload sat on the public disk
 * forever: no row ever pointed at it, the deleting() hook can never see
 * that path, and no prune command covers storage/.
 *
 * The fix makes the insert and its #225 fan-out one transaction — a post
 * is now never half-published with zero bells — wrapped in a catch that
 * frees THIS request's file before rethrowing. The honest-path tests pin
 * that the happy flow is untouched: file, row, and admin bell as before.
 */
class StoreUploadSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_a_failed_alert_insert_sweeps_the_stored_image(): void
    {
        $user = User::factory()->create();

        // Simulate the post-disk-write failure at its earliest point: the
        // upload has already landed on the fake disk when create() dies.
        Alert::creating(function (): void {
            throw new RuntimeException('simulated insert failure');
        });

        try {
            $this->withoutExceptionHandling();
            $this->actingAs($user)->post('/alerts', [
                'title' => 'Cảnh báo chết giữa chừng',
                'description' => 'd',
                'confirmCheckbox' => 'yes',
                'image' => UploadedFile::fake()->image('x.png'),
            ]);
            $this->fail('the simulated insert failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated insert failure', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        // The pre-fix shape leaves exactly one file here, referenced by no
        // row, forever: no row points at it, so deleting() can never free
        // it. Post-fix the catch sweeps it before rethrowing.
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, DB::table('alerts')->count());
    }

    public function test_a_failed_experience_insert_sweeps_the_stored_avatar(): void
    {
        $user = User::factory()->create();

        Experience::creating(function (): void {
            throw new RuntimeException('simulated insert failure');
        });

        try {
            $this->withoutExceptionHandling();
            $this->actingAs($user)->post('/experiences', [
                'title' => 'Bài chia sẻ chết giữa chừng',
                'content' => 'c',
                'name' => 'Người gửi',
                'avatar' => UploadedFile::fake()->image('a.png'),
            ]);
            $this->fail('the simulated insert failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated insert failure', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, DB::table('experiences')->count());
    }

    public function test_honest_alert_creation_still_keeps_file_row_and_bell(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['isAdmin' => true]);

        $this->actingAs($user)->post('/alerts', [
            'title' => 'Cảnh báo trung thực',
            'description' => 'd',
            'confirmCheckbox' => 'yes',
            'image' => UploadedFile::fake()->image('x.png'),
        ])->assertRedirect(route('alerts.create'));

        $alert = Alert::first();
        $this->assertNotNull($alert);
        Storage::disk('public')->assertExists($alert->image);
        // The fan-out inside the transaction still rings the admin bell
        // once the commit succeeds.
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_honest_experience_creation_still_keeps_file_row_and_bell(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['isAdmin' => true]);

        $this->actingAs($user)->post('/experiences', [
            'title' => 'Bài chia sẻ trung thực',
            'content' => 'c',
            'name' => 'Người gửi',
            'avatar' => UploadedFile::fake()->image('a.png'),
        ])->assertRedirect(route('experiences.index'));

        $exp = Experience::first();
        $this->assertNotNull($exp);
        Storage::disk('public')->assertExists($exp->avatar);
        $this->assertSame(1, DB::table('notifications')->count());
    }
}
