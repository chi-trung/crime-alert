<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #287: both update() controllers gate the demote-bell on the route
 * binding's PRE-TRANSACTION status read ($demotesIntoQueue), but the guarded
 * UPDATE deliberately excludes status from its guard (#265's documented
 * shape) and writes 'pending' unconditionally for non-admins. If an admin's
 * approve()/reject() commits between the binding read and that UPDATE, the
 * row the owner last saw as 'pending' is actually 'approved'/'rejected' —
 * and this request resurrects it into the queue with zero bells, violating
 * the invariant #225/#257 exist to enforce ("every arrival in the queue must
 * name itself"). The L486-491/L223-228 "residual" notes waved this off as
 * answered by approve()/reject()'s own guards; those only stop double-clicked
 * moderation, not an un-rebroadcast re-queue.
 *
 * The interleave is armed with the repo's #163 retrieved() idiom: the route
 * binding hydrates the row while it is 'pending' (so the stale gate computes
 * false), then the event moves the live row to 'approved' (or 'rejected')
 * behind that read — the mid-flight commit, deterministic on both dialects
 * (the rival's write is this transaction's own statement, which is all the
 * code under test can observe either way). Without the fix the fan-out count
 * is 0 while the row sits 'pending' with the editor's new text; with the fix
 * the gate is recomputed from a current read at the top of the transaction
 * and every admin is rung.
 */
class PendingDemotionMidFlightBellTest extends TestCase
{
    use RefreshDatabase;

    private function ownersAndAdmins(): array
    {
        $owner = User::factory()->create();
        // The PUT routes sit behind 'verified' (routes/web.php group) — same
        // email-verification step PendingDemotionReNotificationTest uses.
        $owner->email_verified_at = now();
        $owner->save();
        foreach ([1, 2, 3] as $i) {
            User::factory()->create(['isAdmin' => true]);
        }

        return [$owner];
    }

    private function bells(): int
    {
        return DB::table('notifications')
            ->where('type', NewPostPendingApprovalNotification::class)
            ->count();
    }

    /**
     * Arm a mid-flight moderation transition on the model's FIRST hydration —
     * the route binding — so the in-memory row stays 'pending' (what the gate
     * reads) while the live row becomes $to the instant the binding read
     * returns. Builder update: no re-hydration, no event recursion.
     */
    private function armMidFlight(string $class, string $to): void
    {
        $armed = true;
        $class::retrieved(function ($model) use (&$armed, $class, $to): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            $class::whereKey($model->id)->update(['status' => $to]);
        });
    }

    public function test_alert_approved_mid_flight_is_not_resurrected_silently(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Cảnh báo chờ duyệt',
            'description' => 'Nội dung',
            'status' => 'pending',
        ]);
        $this->armMidFlight(Alert::class, 'approved');
        $this->assertSame(0, $this->bells());

        // Owner edits while an admin approves the OLD text mid-flight: the
        // binding saw 'pending' (gate false before the #287 fix), but the row
        // this UPDATE overwrites is 'approved' — rewritten, unreviewed text
        // lands back in the queue and must ring every admin.
        $this->actingAs($owner)
            ->put("/alerts/{$alert->id}", [
                'title' => 'Cảnh báo đã sửa',
                'description' => 'Nội dung mới',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $alert->fresh()->status);
        $this->assertSame(3, $this->bells(), 'a mid-flight approve must not silence the re-queue bell (#287)');
    }

    public function test_alert_rejected_mid_flight_is_not_resurrected_silently(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Cảnh báo chờ duyệt',
            'description' => 'Nội dung',
            'status' => 'pending',
        ]);
        $this->armMidFlight(Alert::class, 'rejected');

        $this->actingAs($owner)
            ->put("/alerts/{$alert->id}", [
                'title' => 'Cảnh báo đã sửa',
                'description' => 'Nội dung mới',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $alert->fresh()->status);
        $this->assertSame(3, $this->bells(), 'a mid-flight reject undone by the owner edit must still bell (#257 invariant, #287 window)');
    }

    public function test_experience_approved_mid_flight_is_not_resurrected_silently(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Người chia sẻ',
            'title' => 'Bài chờ duyệt',
            'content' => 'Nội dung',
            'status' => 'pending',
        ]);
        $this->armMidFlight(Experience::class, 'approved');
        $this->assertSame(0, $this->bells());

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Người chia sẻ',
                'title' => 'Bài đã sửa',
                'content' => 'Nội dung mới',
            ])
            ->assertRedirect(route('experiences.show', $experience));

        $this->assertSame('pending', $experience->fresh()->status);
        $this->assertSame(3, $this->bells(), 'ExperienceController::update gates the bell the same stale way (#287)');
    }

    public function test_experience_rejected_mid_flight_is_not_resurrected_silently(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Người chia sẻ',
            'title' => 'Bài chờ duyệt',
            'content' => 'Nội dung',
            'status' => 'pending',
        ]);
        $this->armMidFlight(Experience::class, 'rejected');

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Người chia sẻ',
                'title' => 'Bài đã sửa',
                'content' => 'Nội dung mới',
            ])
            ->assertRedirect(route('experiences.show', $experience));

        $this->assertSame('pending', $experience->fresh()->status);
        $this->assertSame(3, $this->bells(), 'a mid-flight reject undone by the owner edit must still bell (#257 invariant, #287 window)');
    }
}
