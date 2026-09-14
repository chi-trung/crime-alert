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
 *
 * Issue #255 extends the file with the sibling race: two REPLACEMENT uploads
 * around each other (double-click, two tabs). The row survives there, so
 * #233's sweep never ran: the loser's blind write overwrote the winner's
 * path and nothing ever freed either orphan. update() is now an
 * image-guarded write, and the last test pins that a stale writer persists
 * nothing, rings no bell, and leaves exactly its own file swept.
 *
 * Issue #265 adds the case #255's guard could not see: two CONTENT-only edits
 * never contend on the image column at all — the keep-branch copies the
 * unchanged value through — so both writers matched the image predicate and
 * the later committer destroyed the earlier payload under two success
 * flashes. The guard now covers every contended column (title, description,
 * location, type, latitude, longitude), pinned by the two tests appended
 * below: one rival moving text, one moving only coordinates.
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

    public function test_a_rival_replacement_committed_mid_flight_makes_the_stale_writer_persist_nothing(): void
    {
        // Issue #255: the sibling of the vanish race — here the row SURVIVES,
        // so #233's sweep never applies. Sequence: R1 hydrates the row
        // (image=alerts/old.png), a rival replacement lands and moves the
        // column to alerts/rival.png, then R1 tries to commit its own new
        // file. Before #255 R1's write was blind: it overwrote the rival's
        // path (two orphans, one destroyed live file) and flashed success.
        // Now the UPDATE is guarded on the exact image value R1 read, the
        // in-transaction re-read spots the mismatch, and R1 must: free its
        // own stored file, touch neither the column nor the rival's file,
        // ring no demote bell, and answer an info reload notice.
        [$owner, $alert] = $this->ownerWithApprovedAlert();

        $armed = true;
        Alert::retrieved(function (Alert $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            $live = Alert::find($model->id);
            if ($live !== null) {
                Storage::disk('public')->put('alerts/rival.png', 'RIVAL');
                $live->image = 'alerts/rival.png';
                $live->save();
            }
        });

        $response = $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Ghi đè của kẻ đến sau',
            'description' => 'd',
            'image' => UploadedFile::fake()->image('mine.png'),
        ]);

        // Not a 404 (the row lives) and not a false success: an honest
        // "somebody else wrote first, reload" redirect.
        $response->assertRedirect();
        $response->assertSessionHas('info', 'Cảnh báo vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');

        // The rival's write stands untouched — column AND title both.
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'image' => 'alerts/rival.png',
            'title' => 'Cảnh báo cũ',
        ]);

        // The disk holds exactly what it held before R1 arrived: R1's own
        // fresh upload is swept, and neither the old nor the rival file was
        // destroyed by a write that persisted nothing.
        $this->assertSame(['alerts/old.png', 'alerts/rival.png'], Storage::disk('public')->allFiles());

        // The stale writer demoted nothing, so no admin bell rang either.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_rival_content_edit_committed_mid_flight_stales_the_text_writer(): void
    {
        // Issue #265: the race #255's image predicate structurally cannot
        // see — two content edits never touch the column, so both matched
        // the guard and the later committer silently destroyed the earlier
        // payload. Sequence: R1 binds the row, a rival rewrite lands and
        // moves ONLY the title, then R1 tries to commit its own title.
        // The content-guarded UPDATE now matches zero rows and the
        // staleness re-read reports 'stale': R1 persists nothing, rings no
        // bell, and answers the info reload notice instead of the old
        // double-success double-fan-out.
        [$owner, $alert] = $this->ownerWithApprovedAlert();

        $armed = true;
        Alert::retrieved(function (Alert $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            // Rival write is deliberately RAW (no model events, no sweeps)
            // — exactly an ordinary concurrent PUT that won first.
            DB::table('alerts')->where('id', $model->id)->update([
                'title' => 'Tiêu đề của kẻ đến trước',
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Tiêu đề của kẻ đến sau',
            'description' => 'd',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info', 'Cảnh báo vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');

        // The rival's title stands — R1's payload destroyed nothing.
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'title' => 'Tiêu đề của kẻ đến trước',
            'image' => 'alerts/old.png',
        ]);

        // The losing writer demoted nothing, so no admin bell rang either.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_rival_coordinate_edit_stales_an_identical_text_writer(): void
    {
        // The latitude/longitude legs of the #265 guard, pinned in
        // isolation: the loser's payload is byte-identical to the current
        // text so every other column reads back equal — only the
        // coordinates differ. Delete latitude/longitude from the guard and
        // this request wins again, destroying the rival's move under a
        // success flash, so only this test can notice the regression.
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $alert->update(['latitude' => 10.5, 'longitude' => 20.25]);
        // Read the attributes back through a FRESH model: on MySQL a DECIMAL
        // round-trips as '10.5000000', and the payload below mirrors what
        // the edit form would re-submit on this dialect — the guard must
        // match that spelling, which is the same dialect trap #31 flagged.
        $live = Alert::find($alert->id);

        $armed = true;
        Alert::retrieved(function (Alert $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            DB::table('alerts')->where('id', $model->id)->update([
                'latitude' => '11.5000000',
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => $live->title,
            'description' => $live->description,
            'latitude' => (string) $live->latitude,
            'longitude' => (string) $live->longitude,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info', 'Cảnh báo vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');

        // The rival's coordinate move stands untouched.
        $this->assertSame('11.5', (string) round((float) DB::table('alerts')->where('id', $alert->id)->value('latitude'), 7));

        $this->assertSame(0, DB::table('notifications')->count());
    }
}
