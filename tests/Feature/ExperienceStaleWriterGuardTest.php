<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #275: ExperienceController::update() ported #233/#247's VANISH arm
 * but never #255/#265's stale-writer half — $experience->update($data) was a
 * blind model write, so two same-owner requests (two tabs; the route's own
 * 5/min throttle budget lets two through) both matched the live row, the
 * later committer destroyed the earlier payload, BOTH flashed success, and —
 * because the winner also left 'pending' — the loser's demote re-read passed
 * and admins received a second bell for content it never wrote. The fix
 * mirrors the alerts doctrine exactly: an UPDATE guarded on the exact
 * title/content/name this request read, plus an in-transaction re-read of
 * those columns that answers 'stale' without trusting any affected-rows
 * count (MySQL reports CHANGED, SQLite MATCHED — #233's dialect split).
 * Deliberately NOT guarded on status: a mid-flight moderation transition is
 * #189's conditional-transition shape on the other side — the same documented
 * residual alerts accepted in #265.
 */
class ExperienceStaleWriterGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Experience}
     */
    private function ownerWithApprovedExperience(): array
    {
        $owner = User::factory()->create();
        $experience = Experience::forceCreate([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'title' => 'Bài cũ',
            'content' => 'c',
            'status' => 'approved',
        ]);

        return [$owner, $experience];
    }

    public function test_a_rival_content_edit_committed_mid_flight_stales_the_late_writer(): void
    {
        // The #265 scenario transplanted: R1 binds the row, a rival rewrite
        // lands (deliberately RAW — no model events — exactly an ordinary
        // concurrent PUT that won first), then R1 tries to commit its own
        // title. The guarded UPDATE matches zero rows, the staleness re-read
        // spots it: R1 persists nothing, rings no bell, and gets the honest
        // reload notice instead of the old success-plus-second-bell.
        User::factory()->admin()->create();
        [$owner, $experience] = $this->ownerWithApprovedExperience();

        $armed = true;
        Experience::retrieved(function (Experience $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            DB::table('experiences')->where('id', $model->id)->update([
                'title' => 'Tiêu đề của kẻ đến trước',
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Tiêu đề của kẻ đến sau',
            'content' => 'c',
            'name' => $owner->name,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('info', 'Bài chia sẻ vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');
        $response->assertSessionMissing('success');

        // The rival's write stands — title untouched by R1, and the row was
        // never demoted behind R1's failed guard (no lost-update AND no
        // phantom queue entry).
        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'title' => 'Tiêu đề của kẻ đến trước',
            'status' => 'approved',
        ]);

        // The loser rang nothing: the #225 fan-out stays gated behind a
        // durable write, so admins see one bell per real demote, never two.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_rival_name_edit_committed_mid_flight_stales_the_late_writer(): void
    {
        // The 'name' leg of the guard in isolation: the loser's payload is
        // byte-identical to the stored title/content, so a guard keyed on
        // those two alone would match and clobber the rival's name move.
        User::factory()->admin()->create();
        [$owner, $experience] = $this->ownerWithApprovedExperience();

        $armed = true;
        Experience::retrieved(function (Experience $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            DB::table('experiences')->where('id', $model->id)->update([
                'name' => 'Tên của kẻ đến trước',
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Bài cũ',
            'content' => 'c',
            'name' => 'Tên của kẻ đến sau',
        ]);

        $response->assertSessionHas('info', 'Bài chia sẻ vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');

        $this->assertDatabaseHas('experiences', [
            'id' => $experience->id,
            'name' => 'Tên của kẻ đến trước',
            'status' => 'approved',
        ]);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_an_ordinary_owner_edit_still_wins_demotes_and_bells_exactly_once(): void
    {
        // The guard must not false-positive on the single-writer path: a
        // normal edit matches its own snapshot, persists, demotes to the
        // queue, and rings exactly one bell per admin.
        $admin = User::factory()->admin()->create();
        [$owner, $experience] = $this->ownerWithApprovedExperience();

        $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Bài sửa bình thường',
            'content' => 'c',
            'name' => $owner->name,
        ])->assertRedirect(route('experiences.show', $experience))
            ->assertSessionHas('success');

        $experience->refresh();
        $this->assertSame('Bài sửa bình thường', $experience->title);
        $this->assertSame('pending', $experience->status);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $admin->id)->count());
    }
}
