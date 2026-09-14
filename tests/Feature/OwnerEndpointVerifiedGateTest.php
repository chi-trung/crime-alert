<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #237: #129's verified-email invariant was enforced only in the
 * store() methods. update()/destroy() on the same resources checked nothing
 * but ownership, so PATCH /profile — which nulls email_verified_at while the
 * session stays authenticated (ProfileController::update) — turned a
 * published account into an unverified one that still held full write rights:
 * a re-edit of an approved alert flipped it back to pending and re-rang every
 * admin the exact NewPostPendingApprovalNotification store() refuses to send
 * (#225's fan-out through a #129-gated door). The six mutating owner
 * endpoints now open with store()'s verbatim gate, and the two fan-out
 * capable PUT routes carry store()'s #165-shaped named throttle lanes.
 */
class OwnerEndpointVerifiedGateTest extends TestCase
{
    use RefreshDatabase;

    /** Verified owner of an approved alert + experience + comment, plus an admin. */
    private array $world;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'Approved alert',
            'description' => 'd',
            'status' => 'approved',
        ]);
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => $owner->name,
            'title' => 'Approved story',
            'content' => 'c',
            'status' => 'approved',
        ]);
        $comment = Comment::create([
            'user_id' => $owner->id,
            'alert_id' => $alert->id,
            'content' => 'Approved comment',
        ]);

        $this->world = compact('owner', 'admin', 'alert', 'experience', 'comment');
    }

    /**
     * The issue's real vector, not a factory shortcut: PATCH /profile with a
     * new email nulls email_verified_at while the session stays alive. The
     * gate tests below actingAs() this same user object with the session's
     * identity, so they exercise the post-email-change state exactly as a
     * browser would.
     */
    private function unverifyViaProfileChange(User $user): void
    {
        $this->actingAs($user)
            ->patch('/profile', ['name' => $user->name, 'email' => 'moved-'.substr(md5((string) $user->id), 0, 8).'@example.test'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->hasVerifiedEmail(), 'PATCH /profile must still un-verify the account (the vector this gate closes)');
    }

    public function test_dashboard_and_every_store_remain_gated_after_a_profile_email_change(): void
    {
        // Positive control for the premise: after the email change the
        // account is unverified and ALREADY barred from the create side
        // (#129). If this ever stops holding, the mutation half of the test
        // is testing a state no request could reach.
        $owner = $this->world['owner'];
        $this->unverifyViaProfileChange($owner);

        $this->actingAs($owner)->post('/alerts', ['title' => 'x', 'description' => 'y', 'confirmCheckbox' => '1'])
            ->assertSessionHas('error');
        $this->assertSame(1, Alert::where('user_id', $owner->id)->count());

        $this->actingAs($owner)->post('/comments', ['alert_id' => $this->world['alert']->id, 'content' => 'nope'])
            ->assertSessionHas('error');
        $this->assertSame(1, Comment::count());
    }

    public function test_unverified_owner_cannot_edit_or_delete_own_alert_experience_or_comment(): void
    {
        $owner = $this->world['owner'];
        $this->unverifyViaProfileChange($owner);
        [$alert, $experience, $comment] = [$this->world['alert'], $this->world['experience'], $this->world['comment']];

        $this->actingAs($owner)->put("/alerts/{$alert->id}", ['title' => 'Rewritten', 'description' => 'd'])
            ->assertSessionHas('error');
        $this->assertSame('Approved alert', $alert->fresh()->title);
        $this->assertSame('approved', $alert->fresh()->status);

        $this->actingAs($owner)->put("/experiences/{$experience->id}", ['title' => 'Rewritten', 'content' => 'c', 'name' => $owner->name])
            ->assertSessionHas('error');
        $this->assertSame('Approved story', $experience->fresh()->title);

        $this->actingAs($owner)->put("/comments/{$comment->id}", ['content' => 'Rewritten'])
            ->assertSessionHas('error');
        $this->assertSame('Approved comment', $comment->fresh()->content);

        // The destroy half: self-deletion is lower-impact but store()
        // refuses the same writes, so the six-method invariant is total.
        $this->actingAs($owner)->delete("/alerts/{$alert->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
        $this->actingAs($owner)->delete("/experiences/{$experience->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('experiences', ['id' => $experience->id]);
        $this->actingAs($owner)->delete("/comments/{$comment->id}")->assertSessionHas('error');
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_email_change_can_no_longer_smuggle_a_requeued_alert_bell_past_the_gate(): void
    {
        // The #225 half of the vector: pre-fix this sequence re-belled every
        // admin from an unverified account — the notification store()
        // guarantees an unverified account never emits.
        $owner = $this->world['owner'];
        $admin = $this->world['admin'];
        $alert = $this->world['alert'];

        $before = DB::table('notifications')
            ->where('notifiable_id', $admin->id)
            ->where('type', NewPostPendingApprovalNotification::class)
            ->count();

        $this->unverifyViaProfileChange($owner);

        $this->actingAs($owner)->put("/alerts/{$alert->id}", ['title' => 'Smuggled rewrite', 'description' => 'd'])
            ->assertSessionHas('error');

        $this->assertSame('approved', $alert->fresh()->status, 'a gated edit must not demote the post');
        $this->assertSame(
            $before,
            DB::table('notifications')
                ->where('notifiable_id', $admin->id)
                ->where('type', NewPostPendingApprovalNotification::class)
                ->count(),
            'unverified account must not fire the admin approval bell'
        );
    }

    public function test_verified_owner_still_reaches_all_six_endpoints(): void
    {
        // Negative control for the fix itself: the gate must bind ONLY
        // unverified accounts. A verified owner keeps the exact previous
        // behaviour on every endpoint the change touches.
        $owner = $this->world['owner'];
        [$alert, $experience, $comment] = [$this->world['alert'], $this->world['experience'], $this->world['comment']];

        $this->actingAs($owner)->put("/alerts/{$alert->id}", ['title' => 'Edited', 'description' => 'd'])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $alert->fresh()->title);

        $this->actingAs($owner)->put("/experiences/{$experience->id}", ['title' => 'Edited', 'content' => 'c', 'name' => $owner->name])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $experience->fresh()->title);

        $this->actingAs($owner)->put("/comments/{$comment->id}", ['content' => 'Edited'])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $comment->fresh()->content);

        $this->actingAs($owner)->delete("/comments/{$comment->id}")->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete("/experiences/{$experience->id}")->assertSessionHasNoErrors();
        $this->actingAs($owner)->delete("/alerts/{$alert->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
    }

    public function test_the_two_fan_out_put_routes_get_named_lanes_independently_of_the_gate(): void
    {
        // The throttle half of #237 is deliberately independent of the gate
        // half: a VERIFIED owner hammering PUT /alerts/{id} is legitimate
        // traffic but still #225 fan-out (one bell per admin per demote), so
        // store()'s #165 bound — 5/min on its own named lane — moves here.
        // The lane name matters: sharing a bucket with alert-create would
        // let edits starve new posts (#147's probe class).
        $owner = $this->world['owner'];
        $alert = $this->world['alert'];
        $experience = $this->world['experience'];

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($owner)->put("/alerts/{$alert->id}", ['title' => "Edit {$i}", 'description' => 'd'])
                ->assertSessionHasNoErrors("edit {$i} unexpectedly rate-limited");
        }
        $this->actingAs($owner)->put("/alerts/{$alert->id}", ['title' => 'Edit 6', 'description' => 'd'])->assertStatus(429);

        // The experiences lane is separate: exhausting alert-update must not
        // burn it (a shared bucket is the bug #147 pinned).
        $this->actingAs($owner)->put("/experiences/{$experience->id}", ['title' => 'Still fine', 'content' => 'c', 'name' => $owner->name])
            ->assertSessionHasNoErrors();

        // And store()'s lane is untouched by the edit traffic: five more
        // fresh posts must pass validation, proving the lanes are distinct
        // counters, not one shared auth-group bucket.
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($owner)->post('/alerts', ['title' => "New {$i}", 'description' => 'd', 'confirmCheckbox' => '1'])
                ->assertSessionHasNoErrors();
        }
    }

    public function test_like_destroy_stays_exempt_by_documentation(): void
    {
        // #147's documented exemption: unlike has no verified gate, its brake
        // is the shared 60/min 'like' lane. If this test ever reddens, the
        // code comment at routes/web.php and this pin must be reconciled —
        // not silently diverging from each other.
        $owner = $this->world['owner'];

        // store() IS gated (#129), so the like must be planted while the
        // account is still verified; the exemption under test is destroy's.
        // postJson because the real callers are the fetch clients (the JSON
        // contract LikeTest pins); like.destroy always answers JSON.
        $this->actingAs($owner)->postJson('/like', ['type' => 'alert', 'id' => $this->world['alert']->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 1]);
        $this->assertDatabaseHas('likes', ['user_id' => $owner->id, 'likeable_type' => Alert::class, 'likeable_id' => $this->world['alert']->id]);

        $this->unverifyViaProfileChange($owner);

        $this->actingAs($owner)->postJson('/like/unlike', ['type' => 'alert', 'id' => $this->world['alert']->id])
            ->assertOk()->assertJson(['success' => true, 'count' => 0]);
        $this->assertDatabaseMissing('likes', ['user_id' => $owner->id, 'likeable_type' => Alert::class, 'likeable_id' => $this->world['alert']->id]);
    }
}
