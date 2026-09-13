<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #87: eight dashboard reads ended in a bare created_at sort (or
 * comments_count then created_at). created_at is second-resolution, so
 * rows from one posting burst are tied and the engine's scan order —
 * insertion order on sqlite — silently decides which rows the LIMIT 3
 * cards and LIMIT 1 "latest" picks deliver. Same fix as #81: desc id
 * tiebreak, newest-first intent preserved.
 *
 * Membership, not ORDER BY strings, is asserted: the views consume these
 * keys directly, so page content is the real contract, and it holds on
 * both CI dialects without matching engine-specific SQL.
 */
class DashboardLatestTiebreakTest extends TestCase
{
    use RefreshDatabase;

    private function seedAlerts(User $user, int $n, string $status = 'approved'): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            // forceCreate: created_at is not fillable, and leaving it to
            // now() keeps every row in the SAME second — the exact tie this
            // test is about.
            $ids[] = Alert::forceCreate([
                'user_id' => $user->id,
                'title' => "A{$i}",
                'description' => 'd',
                'status' => $status,
            ])->id;
        }

        return $ids;
    }

    private function seedExperiences(User $user, int $n, string $status = 'approved'): array
    {
        $ids = [];
        for ($i = 1; $i <= $n; $i++) {
            $ids[] = Experience::forceCreate([
                'user_id' => $user->id,
                'name' => 'N',
                'title' => "E{$i}",
                'content' => 'c',
                'status' => $status,
            ])->id;
        }

        return $ids;
    }

    public function test_admin_latest_single_row_picks_are_the_highest_ids(): void
    {
        $admin = User::factory()->admin()->create();
        $alertIds = $this->seedAlerts($admin, 4);
        $expIds = $this->seedExperiences($admin, 4);
        $srIds = [];
        foreach ([1, 2] as $i) {
            $srIds[] = SupportRequest::forceCreate([
                'user_id' => $admin->id,
                'subject' => "S{$i}",
                'status' => 'open',
            ])->id;
        }

        // All six rows per table share one created_at second, so each
        // "latest" pick must deterministically be the highest id.
        $this->actingAs($admin)->get('/dashboard')
            ->assertViewHas('latestAlert', fn ($a) => $a->id === max($alertIds))
            ->assertViewHas('latestExperience', fn ($e) => $e->id === max($expIds))
            ->assertViewHas('latestSupportRequest', fn ($s) => $s->id === max($srIds));
    }

    public function test_user_latest_single_row_picks_are_the_highest_ids(): void
    {
        $user = User::factory()->create();
        $alertIds = $this->seedAlerts($user, 3);
        $expIds = $this->seedExperiences($user, 3);
        SupportRequest::forceCreate(['user_id' => $user->id, 'subject' => 'S1', 'status' => 'open']);
        $mine = SupportRequest::forceCreate(['user_id' => $user->id, 'subject' => 'S2', 'status' => 'open']);
        // Another user's newer-but-tied request must not leak into the
        // per-user pick — guards the where('user_id') leg staying intact.
        $other = User::factory()->create();
        SupportRequest::forceCreate(['user_id' => $other->id, 'subject' => 'X', 'status' => 'open']);

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('myLatest', fn ($a) => $a->id === max($alertIds))
            ->assertViewHas('myExperience', fn ($e) => $e->id === max($expIds))
            ->assertViewHas('latestSupportRequest', fn ($s) => $s->id === $mine->id);
    }

    public function test_top_three_cards_take_the_highest_ids_among_ties(): void
    {
        $admin = User::factory()->admin()->create();
        $alertIds = $this->seedAlerts($admin, 5);
        $expIds = $this->seedExperiences($admin, 5);

        // Zero comments on every row: comments_count ties too, so the whole
        // sort key is shared and LIMIT 3 must select the top three ids.
        $expected = array_slice(array_reverse($alertIds), 0, 3);
        $expectedExp = array_slice(array_reverse($expIds), 0, 3);

        $this->actingAs($admin)->get('/dashboard')
            ->assertViewHas('topAlerts', fn ($list) => $expected === $list->pluck('id')->all())
            ->assertViewHas('topExperiences', fn ($list) => $expectedExp === $list->pluck('id')->all());
    }

    public function test_commented_top_cards_still_tiebreak_within_equal_counts(): void
    {
        // Two discussion tiers (2 comments vs 1) so the withCount join
        // actually orders — the tiebreak governs only rows WITHIN a tier,
        // which is where the old scan order decided membership at 3/4.
        $admin = User::factory()->admin()->create();
        // 4 approved alerts, ids ascending: $ids[3] newest. All share one
        // created_at second (forceCreate, not fillable — see helpers).
        $ids = [];
        // 4 approved alerts, ids ascending: $ids[3] newest.
        for ($i = 0; $i < 4; $i++) {
            // forceCreate on purpose: created_at must stay in one second so
            // the three 2-comment rows are fully tied below comments_count.
            $ids[] = Alert::forceCreate([
                'user_id' => $admin->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'approved',
            ])->id;
        }
        // Newest three get 2 comments, the oldest gets 1.
        foreach ($ids as $idx => $id) {
            $alert = Alert::find($id);
            $n = $idx === 0 ? 1 : 2;
            foreach (range(1, $n) as $c) {
                Comment::forceCreate([
                    'alert_id' => $id,
                    'user_id' => $admin->id,
                    'content' => "c{$c}",
                ]);
            }
        }

        // comments_count puts A1 (1 comment) last; the remaining three tie
        // at 2 and share created_at, so order among them is id DESC.
        $this->actingAs($admin)->get('/dashboard')
            ->assertViewHas('topAlerts', fn ($list) => [$ids[3], $ids[2], $ids[1]] === $list->pluck('id')->all());
        unset($hot);
    }
}
