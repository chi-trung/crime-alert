<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #315 (r15/auth-lanes): #165/#237 put named throttle lanes on
 * alerts.store/update and experiences.update and #297 closed the identical
 * gap for the comment siblings, but the two DELETE routes stayed bare — the
 * last unthrottled user-facing write lanes left in the auth group. #311 made
 * them strictly MORE expensive, not less: every destroy now runs the
 * hook sweep plus at least one fixed-point sweep round over the unindexed
 * notifications TEXT column, and on MySQL the acquiring DELETE also blocks
 * behind any rival #153 fan-out's row lock, so repeated destroys serialize.
 * An abusive verified user could therefore keep requesting an unbounded
 * queue of full-table LIKE sweeps per minute.
 *
 * 5,1 matches the alerts/experiences store+update lane already carried by
 * the same route family; the admin-side siblings stay bare per this repo's
 * consistent moderation-lane doctrine (the whole admin group is
 * unthrottled — approve/reject included).
 */
class DestroyThrottleLaneTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(User $owner): Alert
    {
        return Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
    }

    private function makeExperience(User $owner): Experience
    {
        return Experience::create(['user_id' => $owner->id, 'name' => 'N', 'title' => 'T', 'content' => 'c', 'status' => 'approved']);
    }

    public function test_alert_destroy_is_throttled_at_five_requests_per_minute(): void
    {
        $user = User::factory()->create();

        // One row per attempt: the binding 404s a second delete of the same
        // alert, but ThrottleRequests counts the ATTEMPT — the #297
        // comment-destroy idiom. The 6th must 429 before the controller.
        $alerts = collect();
        for ($i = 1; $i <= 6; $i++) {
            $alerts->push($this->makeAlert($user));
        }

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->delete(route('alerts.destroy', $alerts[$i]))
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->delete(route('alerts.destroy', $alerts[5]))
            ->assertStatus(429);

        // The throttled attempt never reached the controller: its row lives.
        $this->assertDatabaseHas('alerts', ['id' => $alerts[5]->id]);
    }

    public function test_experience_destroy_is_throttled_at_five_requests_per_minute(): void
    {
        $user = User::factory()->create();

        $experiences = collect();
        for ($i = 1; $i <= 6; $i++) {
            $experiences->push($this->makeExperience($user));
        }

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->delete(route('experiences.destroy', $experiences[$i]))
                ->assertStatus(302);
        }

        $this->actingAs($user)
            ->delete(route('experiences.destroy', $experiences[5]))
            ->assertStatus(429);

        $this->assertDatabaseHas('experiences', ['id' => $experiences[5]->id]);
    }

    public function test_the_two_destroy_lanes_do_not_share_counters(): void
    {
        // #147's bucket-prefix doctrine: without a distinct third argument
        // both limiters key by user id alone and grinding one destroy lane
        // starves the other (and the store/update lanes). Two prefixes, two
        // budgets.
        $user = User::factory()->create();

        $alerts = collect();
        for ($i = 1; $i <= 6; $i++) {
            $alerts->push($this->makeAlert($user));
        }
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->delete(route('alerts.destroy', $alerts[$i]));
        }
        $this->actingAs($user)->delete(route('alerts.destroy', $alerts[5]))
            ->assertStatus(429);

        // Alert-destroy lane exhausted; the experience lane must still answer.
        $experience = $this->makeExperience($user);
        $this->actingAs($user)->delete(route('experiences.destroy', $experience))
            ->assertStatus(302);
    }

    public function test_destroy_throttles_are_per_user_and_do_not_starve_the_update_lane(): void
    {
        $owner = User::factory()->create();
        $bystander = User::factory()->create();

        $alerts = collect();
        for ($i = 1; $i <= 6; $i++) {
            $alerts->push($this->makeAlert($owner));
        }
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($owner)->delete(route('alerts.destroy', $alerts[$i]));
        }

        // The limiter keys by authenticated user id: the bystander keeps a
        // full destroy budget on their own row...
        $theirs = $this->makeAlert($bystander);
        $this->actingAs($bystander)->delete(route('alerts.destroy', $theirs))
            ->assertStatus(302);

        // ...and the owner's exhausted DESTROY bucket never bleeds into their
        // update lane (the #147 shared-counter bug in one assertion).
        $mine = $this->makeAlert($owner);
        $this->actingAs($owner)->put(route('alerts.update', $mine), [
            'title' => 'edited', 'description' => 'd',
        ])->assertStatus(302);
    }
}
