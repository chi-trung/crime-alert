<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #225: store() guarantees every 'pending' post rings
 * NewPostPendingApprovalNotification for every admin, but the SECOND entry
 * into the queue — update() demoting an approved post after its owner
 * rewrote it (issue #23's rule) — notified nobody. The re-queued rewrite sat
 * on no bell: the reviewer #23 promises had to diff /admin/alerts by hand to
 * find it. Both controllers now fan out on an approved->pending demotion,
 * inside a transaction with a re-read before ringing (#139 idiom), and the
 * gate keeps already-pending edits from re-belling.
 */
class PendingDemotionReNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function ownersAndAdmins(): array
    {
        $owner = User::factory()->create();
        $owner->email_verified_at = now();
        $owner->save();
        $admins = collect();
        foreach ([1, 2, 3] as $i) {
            $admins->push(User::factory()->create(['isAdmin' => true]));
        }

        return [$owner, $admins];
    }

    private function bells(): int
    {
        return \DB::table('notifications')
            ->where('type', NewPostPendingApprovalNotification::class)
            ->count();
    }

    public function test_owner_edit_demoting_an_approved_alert_bells_every_admin(): void
    {
        [$owner, $admins] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Canh bao da duyet',
            'description' => 'Noi dung goc',
            'status' => 'approved',
        ]);
        $this->assertSame(0, $this->bells());

        $this->actingAs($owner)
            ->put("/alerts/{$alert->id}", [
                'title' => 'Canh bao da sua',
                'description' => 'Noi dung moi',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $alert->fresh()->status);
        // Exactly one bell per admin — store() would have produced the same
        // count for a fresh pending post; the asymmetry is what #225 closes.
        $this->assertSame(3, $this->bells());
        foreach ($admins as $admin) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'type' => NewPostPendingApprovalNotification::class,
            ]);
        }
        // The bell data must name the post that came back, not just a count.
        $row = (array) json_decode(\DB::table('notifications')->value('data'), true);
        $this->assertSame($alert->id, $row['post_id']);
        $this->assertSame('alert', $row['post_type']);
        $this->assertSame('Canh bao da sua', $row['post_title']);
    }

    public function test_edit_of_an_already_pending_alert_bells_nobody(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Canh bao cho duyet',
            'description' => 'Noi dung',
            'status' => 'pending',
        ]);
        $before = $this->bells();

        // The gate: a pending post edited again was already in the queue and
        // already belling; re-ringing per keystroke-save would bury the
        // original bell under duplicates.
        $this->actingAs($owner)
            ->put("/alerts/{$alert->id}", [
                'title' => 'Sua tiep',
                'description' => 'Noi dung moi hon',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $alert->fresh()->status);
        $this->assertSame($before, $this->bells());
    }

    public function test_admin_edit_of_an_approved_alert_bells_nobody(): void
    {
        [$owner, $admins] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Canh bao da duyet',
            'description' => 'Noi dung',
            'status' => 'approved',
        ]);

        // #73's rule stands: an admin typo-fix keeps 'approved' and never
        // re-queues, so there is nothing to announce.
        $this->actingAs($admins->first())
            ->put("/alerts/{$alert->id}", [
                'title' => 'Sua lai chinh ta',
                'description' => 'Noi dung',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('approved', $alert->fresh()->status);
        $this->assertSame(0, $this->bells());
    }

    public function test_owner_edit_resubmitting_a_rejected_alert_bells_every_admin(): void
    {
        // Issue #257: the gate read `=== 'approved'`, so the OTHER
        // into-pending transition — a rejected post rewritten by its owner —
        // flipped status silently. The reject decision was undone with zero
        // bells: no reviewer learned the post they personally rejected had
        // just come back to the queue, violating #225's stated invariant
        // that every arrival in the queue must name itself.
        [$owner] = $this->ownersAndAdmins();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Canh bao bi tu choi',
            'description' => 'Noi dung cu',
            'status' => 'rejected',
        ]);
        $this->assertSame(0, $this->bells());

        $this->actingAs($owner)
            ->put("/alerts/{$alert->id}", [
                'title' => 'Canh bao da sua lai',
                'description' => 'Noi dung moi',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('pending', $alert->fresh()->status);
        // ownersAndAdmins() seeds exactly three admins; the count is the
        // fan-out, the payload below is the naming #225 demands.
        $this->assertSame(3, $this->bells(), 'a rejected->pending rewrite re-enters the queue and must ring');
        $row = (array) json_decode(\DB::table('notifications')->value('data'), true);
        $this->assertSame('Canh bao da sua lai', $row['post_title'], 'the bell names the NEW content');
    }

    public function test_owner_edit_demoting_an_approved_experience_bells_every_admin(): void
    {
        [$owner, $admins] = $this->ownersAndAdmins();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Nguoi chia se',
            'title' => 'Bai da duyet',
            'content' => 'Noi dung goc',
            'status' => 'approved',
        ]);
        $this->assertSame(0, $this->bells());

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Nguoi chia se',
                'title' => 'Bai da sua',
                'content' => 'Noi dung moi',
            ])
            ->assertRedirect(route('experiences.show', $experience));

        $this->assertSame('pending', $experience->fresh()->status);
        $this->assertSame(3, $this->bells());
        foreach ($admins as $admin) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_id' => $admin->id,
                'type' => NewPostPendingApprovalNotification::class,
            ]);
        }
        $row = (array) json_decode(\DB::table('notifications')->value('data'), true);
        $this->assertSame($experience->id, $row['post_id']);
        $this->assertSame('experience', $row['post_type']);
    }

    public function test_edit_of_an_already_pending_experience_bells_nobody(): void
    {
        [$owner] = $this->ownersAndAdmins();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Nguoi chia se',
            'title' => 'Bai cho duyet',
            'content' => 'Noi dung',
            'status' => 'pending',
        ]);
        $before = $this->bells();

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Nguoi chia se',
                'title' => 'Sua tiep',
                'content' => 'Noi dung moi hon',
            ])
            ->assertRedirect(route('experiences.show', $experience));

        $this->assertSame('pending', $experience->fresh()->status);
        $this->assertSame($before, $this->bells());
    }

    public function test_owner_edit_resubmitting_a_rejected_experience_bells_every_admin(): void
    {
        // Issue #257: ExperienceController::update() gates the same way as
        // the alerts one (=== 'approved'), so the rejected->pending rewrite
        // was silent there too. Same invariant, same fix.
        [$owner] = $this->ownersAndAdmins();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Nguoi chia se',
            'title' => 'Bai bi tu choi',
            'content' => 'Noi dung cu',
            'status' => 'rejected',
        ]);
        $this->assertSame(0, $this->bells());

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Nguoi chia se',
                'title' => 'Bai da sua lai',
                'content' => 'Noi dung moi',
            ])
            ->assertRedirect(route('experiences.show', $experience));

        $this->assertSame('pending', $experience->fresh()->status);
        $this->assertSame(3, $this->bells(), 'a rejected->pending rewrite re-enters the queue and must ring');
        $row = (array) json_decode(\DB::table('notifications')->value('data'), true);
        $this->assertSame('Bai da sua lai', $row['post_title']);
        $this->assertSame('experience', $row['post_type']);
    }
}
