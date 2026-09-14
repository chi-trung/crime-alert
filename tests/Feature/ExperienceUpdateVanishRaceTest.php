<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #247: ExperienceController::update() is the sibling of
 * AlertController::update() and shared its #225 transaction — but not its
 * #233 vanish arm. When a concurrent DELETE (admin moderation, the owner's
 * own destroy, or the profile-removal sweep) removed the row between route
 * binding and the transaction, $experience->update() was a silent no-op, and
 * the request answered with a 302 to experiences.show for a row that no
 * longer exists — a success flash in the session over an edit that persisted
 * nothing, landing the user on a dead show page. Experiences carry no
 * replacement image, so unlike alerts there is no orphan file to sweep; the
 * only fix needed is the exists() re-read inside the transaction and an
 * honest 404.
 */
class ExperienceUpdateVanishRaceTest extends TestCase
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

    /**
     * Arm the race: the first time an Experience row is hydrated (the route
     * binding of the owner's PUT), delete the live row through a full model
     * delete so the deleting() hooks fire exactly like an admin's DELETE
     * committing mid-flight. The guard flag keeps the delete's own re-hydration
     * from re-entering the sweep.
     */
    private function armRace(): void
    {
        $armed = true;
        Experience::retrieved(function (Experience $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            $live = Experience::find($model->id);
            if ($live !== null) {
                $live->delete();
            }
        });
    }

    public function test_a_delete_landing_mid_update_is_404_not_a_false_success(): void
    {
        [$owner, $experience] = $this->ownerWithApprovedExperience();
        $this->armRace();

        $response = $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Bài sửa giữa lúc xóa',
            'content' => 'c',
            'name' => $owner->name,
        ]);

        // Honest outcome: the row vanished under the edit, so the edit did
        // not happen — 404, not a 302 + success flash to a dead show page.
        $response->assertNotFound();

        // The vanished row must not sit in the session as a completed edit.
        $response->assertSessionMissing('success');

        // And a vanished row must not re-queue a moderation bell either —
        // the #225 demote fan-out is gated behind the same re-read.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_living_row_still_updates_and_demotes_normally(): void
    {
        // The new exists() arm must not false-positive: an ordinary owner
        // edit on a living approved row keeps #73's demote-to-pending and
        // #225's admin re-bell intact, with the success redirect. (An admin
        // must exist to receive the bell — the fan-out is
        // where('isAdmin', true).)
        User::factory()->admin()->create();
        [$owner, $experience] = $this->ownerWithApprovedExperience();

        $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Bài sửa bình thường',
            'content' => 'c',
            'name' => $owner->name,
        ])->assertRedirect(route('experiences.show', $experience));

        $experience->refresh();
        $this->assertSame('Bài sửa bình thường', $experience->title);
        $this->assertSame('pending', $experience->status);
        $this->assertGreaterThan(0, DB::table('notifications')->count());
    }
}
