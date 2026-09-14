<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #233: the replacement branch of AlertController::update() stored the
 * NEW image on the public disk before the transaction that persists its path,
 * and a disk write cannot roll back. When a concurrent DELETE (admin
 * moderation, the owner's own destroy, or ProfileController's account sweep)
 * removed the row between route binding and persistence, the UPDATE matched
 * nothing, the transaction still committed, the user saw "Cập nhật cảnh báo
 * thành công!" for an edit that saved nothing — and the fresh file was
 * referenced by no row: Alert's deleting() hook only unlinks the OLD path read
 * from the row, which the controller had already deleted, so the new file sat
 * on the public disk forever. update() now re-reads the row inside the
 * transaction; a vanished row means the stored file is removed and the answer
 * is an honest 404.
 */
class AlertReplacementOrphanRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @return array{0: User, 1: Alert}
     */
    private function ownerWithApprovedAlert(): array
    {
        $owner = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id,
            'title' => 'Cảnh báo cũ',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/old.png',
        ]);
        Storage::disk('public')->put('alerts/old.png', 'OLD');

        return [$owner, $alert];
    }

    /**
     * Arm the race: the first time an Alert row is hydrated (the route
     * binding of the owner's PUT), delete the live row through a full model
     * delete — the deleting() hook fires exactly like the admin's
     * DELETE /admin/alerts/{id} committing mid-flight. The guard flag keeps
     * update()'s own in-transaction re-read from re-entering the sweep.
     */
    private function armRace(): void
    {
        $armed = true;
        Alert::retrieved(function (Alert $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            $live = Alert::find($model->id);
            if ($live !== null) {
                $live->delete();
            }
        });
    }

    public function test_a_delete_landing_mid_update_orphans_no_new_file_and_says_404(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $this->armRace();

        $response = $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Cảnh báo sửa giữa lúc xóa',
            'description' => 'd',
            'image' => UploadedFile::fake()->image('new.png'),
        ]);

        // Honest outcome: the row vanished under the edit, so the edit did
        // not happen — 404, not a success flash.
        $response->assertNotFound();

        // The orphan is gone: the old file left with the deleting() hook,
        // the replacement the race stored is swept by the fix. Before #233
        // this disk held the fresh alerts/<hash>.png with no row pointing
        // at it, permanently.
        $this->assertSame([], Storage::disk('public')->allFiles());

        // A vanished row must not re-queue a bell either.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_serial_replacement_still_swaps_the_files(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Cảnh báo đổi ảnh bình thường',
            'description' => 'd',
            'image' => UploadedFile::fake()->image('new.png'),
        ])->assertRedirect(route('dashboard'));

        $alert->refresh();
        $this->assertNotSame('alerts/old.png', $alert->image);
        Storage::disk('public')->assertMissing('alerts/old.png');
        Storage::disk('public')->assertExists($alert->image);
    }

    public function test_a_raced_remove_image_edit_is_404_not_a_false_success(): void
    {
        // The remove_image branch cannot orphan (it deletes the file the row
        // itself references), but before #233 it still answered a vanished
        // row with the success redirect the issue calls "a flash over an
        // edit that persisted nothing".
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $this->armRace();

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Cảnh báo xóa ảnh giữa lúc xóa',
            'description' => 'd',
            'remove_image' => '1',
        ])->assertNotFound();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_resubmitting_identical_content_still_succeeds(): void
    {
        // The MySQL footgun behind the exists() re-read (documented in the
        // controller): MySQL UPDATEs report CHANGED rows, so a second submit
        // of byte-identical content legitimately affects 0 rows. An
        // affected-rows guard would 404 a living row; the re-read does not.
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $payload = [
            'title' => 'Giống hệt nhau',
            'description' => 'd',
        ];

        $this->actingAs($owner)->put("/alerts/{$alert->id}", $payload)->assertRedirect(route('dashboard'));
        // Identical second submit: row alive, nothing changes — still a 302.
        $this->actingAs($owner)->put("/alerts/{$alert->id}", $payload)->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'title' => 'Giống hệt nhau',
            'status' => 'pending',
        ]);
    }
}
