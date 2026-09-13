<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #189: approve()/reject() on both moderation pairs used to write the
 * status unconditionally, so a click from a stale admin page (the UI renders
 * the buttons only while the row is pending and never auto-refreshes) could
 * silently un-approve a public scam warning or resurrect a rejected one —
 * every time answered with the normal success flash. The guards now mirror
 * #98's support-close contract and the atomic conditional UPDATE of
 * #139/#153: only pending rows transition; anything else gets an info flash.
 */
class ModerationTransitionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function alert(User $user, string $status): Alert
    {
        return Alert::create(['user_id' => $user->id, 'title' => 'a', 'description' => 'd', 'status' => $status]);
    }

    private function experience(User $user, string $status): Experience
    {
        return Experience::create(['user_id' => $user->id, 'name' => 'Na', 'title' => 'T', 'content' => 'C', 'status' => $status]);
    }

    public function test_rejecting_an_already_approved_alert_does_not_unapprove_it(): void
    {
        $admin = $this->admin();
        $alert = $this->alert($admin, 'approved');

        $this->actingAs($admin)
            ->post("/admin/alerts/{$alert->id}/reject")
            ->assertRedirect()
            ->assertSessionHas('info')
            ->assertSessionMissing('success');

        $this->assertSame('approved', $alert->fresh()->status, 'a stale reject click must not un-publish an approved alert');
    }

    public function test_approving_a_rejected_alert_does_not_resurrect_it(): void
    {
        $admin = $this->admin();
        $alert = $this->alert($admin, 'rejected');

        $this->actingAs($admin)
            ->post("/admin/alerts/{$alert->id}/approve")
            ->assertSessionHas('info')
            ->assertSessionMissing('success');

        $this->assertSame('rejected', $alert->fresh()->status, 'a stale approve click must not resurrect a rejected alert');
    }

    public function test_pending_to_approved_transition_still_succeeds_with_success_flash(): void
    {
        // Positive control: the guard must not break the intended path —
        // pre-fix and post-fix this one passes identically.
        $admin = $this->admin();
        $alert = $this->alert($admin, 'pending');

        $this->actingAs($admin)
            ->post("/admin/alerts/{$alert->id}/approve")
            ->assertSessionHas('success');

        $this->assertSame('approved', $alert->fresh()->status);
    }

    public function test_alert_experience_moderation_accepts_no_other_state_transition(): void
    {
        $admin = $this->admin();
        foreach ([
            ['pending', 'approved'], ['pending', 'rejected'],
            ['approved', 'rejected'], ['approved', 'approved'],
            ['rejected', 'approved'], ['rejected', 'rejected'],
        ] as [$from, $to]) {
            $row = $this->alert($admin, $from);
            // Route segments are verbs, not statuses: /approve, /reject.
            $action = $to === 'approved' ? 'approve' : 'reject';
            $this->actingAs($admin)->post("/admin/alerts/{$row->id}/{$action}");
            $this->assertSame($from === 'pending' ? $to : $from, $row->fresh()->status, "alert {$from} -> {$to}");
        }
    }

    public function test_experience_approve_reject_carry_the_same_guard(): void
    {
        $admin = $this->admin();

        // Stale un-moderation: reject on an approved experience must no-op.
        $approved = $this->experience($admin, 'approved');
        $this->actingAs($admin)
            ->post("/admin/experiences/{$approved->id}/reject")
            ->assertSessionHas('info')
            ->assertSessionMissing('success');
        $this->assertSame('approved', $approved->fresh()->status);

        // Stale resurrection: approve on a rejected experience must no-op.
        $rejected = $this->experience($admin, 'rejected');
        $this->actingAs($admin)
            ->post("/admin/experiences/{$rejected->id}/approve")
            ->assertSessionHas('info')
            ->assertSessionMissing('success');
        $this->assertSame('rejected', $rejected->fresh()->status);

        // Intended paths still work.
        $pending = $this->experience($admin, 'pending');
        $this->actingAs($admin)->post("/admin/experiences/{$pending->id}/approve")->assertSessionHas('success');
        $this->assertSame('approved', $pending->fresh()->status);
    }
}
