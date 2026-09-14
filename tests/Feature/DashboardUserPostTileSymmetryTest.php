<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #238: forUser() built the "Tổng bài viết" tile's "X/Y được duyệt"
 * fraction from two different definitions of "my post" — $myAlertsThisMonth
 * was pre-filtered to status='approved' while $myExperiencesThisMonth carried
 * every status. A pending/rejected alert was invisible to BOTH numbers, while
 * a pending/rejected experience inflated both. The same rendered page then
 * contradicted itself: the "cảnh báo mới nhất" card shows a pending alert with
 * its "Chờ duyệt" badge even as the tile claims perfect approval. Both content
 * types now share one rule (count every post once, count approved separately),
 * mirroring forAdmin()'s separate total/approved reads.
 */
class DashboardUserPostTileSymmetryTest extends TestCase
{
    use RefreshDatabase;

    private function seedPosts(User $user, array $alerts, array $experiences): void
    {
        $month = now()->startOfMonth()->addDay();
        foreach ($alerts as $i => [$status, $title]) {
            Alert::forceCreate([
                'user_id' => $user->id,
                'title' => $title,
                'description' => 'd',
                'status' => $status,
                'created_at' => $month->copy()->addMinutes($i),
            ]);
        }
        foreach ($experiences as $i => [$status, $title]) {
            // created_at is not in Experience::$fillable (the idiom #227
            // established for Alert), so forceCreate is paired with an
            // explicit touch rather than the old create() + a shared now().
            $exp = Experience::forceCreate([
                'user_id' => $user->id,
                'name' => $user->name,
                'title' => $title,
                'content' => 'c',
                'status' => $status,
            ]);
            $exp->created_at = $month->copy()->addMinutes(10 + $i);
            $exp->save();
        }
    }

    public function test_the_fraction_counts_pending_alerts_and_pending_experiences_identically(): void
    {
        // The exact contradiction the issue describes: one approved + one
        // pending alert AND one approved + one pending experience. The tile
        // must read 2/4 — a pending post of EITHER type lands in the
        // denominator, and neither type is silently dropped.
        $user = User::factory()->create();
        $this->seedPosts(
            $user,
            [['approved', 'A-ok'], ['pending', 'A-pend']],
            [['approved', 'E-ok'], ['pending', 'E-pend']],
        );

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('totalPosts', 4)
            ->assertViewHas('totalApprovedPosts', 2);
    }

    public function test_a_pending_alert_is_not_invisible_to_both_numbers(): void
    {
        // The half the old test never pinned: an approved + a pending alert
        // with NO experience used to render "1/1 được duyệt" — a perfect-
        // approval claim over a user whose second post sits unapproved and is
        // displayed on the very same page.
        $user = User::factory()->create();
        $this->seedPosts($user, [['approved', 'A-ok'], ['pending', 'A-pend']], []);

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('totalPosts', 2)
            ->assertViewHas('totalApprovedPosts', 1);
    }

    public function test_a_pending_experience_no_longer_inflates_the_denominator(): void
    {
        // Symmetric to the above from the experience side: one approved
        // experience + one pending experience is 1/2, not 2/2. The alert
        // half used to be excluded from totalPosts while the experience half
        // was not; now both are counted the same way.
        $user = User::factory()->create();
        $this->seedPosts($user, [], [['approved', 'E-ok'], ['pending', 'E-pend']]);

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('totalPosts', 2)
            ->assertViewHas('totalApprovedPosts', 1);
    }

    public function test_a_rejected_alert_counts_like_any_other_post(): void
    {
        // rejected is neither approved nor pending, but it is still a post the
        // user filed — the tile must include it in the total and exclude it
        // from the approved numerator, identically to experiences.
        $user = User::factory()->create();
        $this->seedPosts($user, [['rejected', 'A-rej'], ['approved', 'A-ok']], [['rejected', 'E-rej']]);

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('totalPosts', 3)
            ->assertViewHas('totalApprovedPosts', 1);
    }

    public function test_a_user_with_no_posts_gets_a_zero_total_not_a_fabricated_fraction(): void
    {
        // Guards the denominator's empty edge: the tile hides the fraction at
        // total 0 (dashboard.blade.php's @if($totalPosts > 0)), and the
        // numbers must be honest zeros rather than the old shape where an
        // all-pending-alert user could still read "0/0".
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertViewHas('totalPosts', 0)
            ->assertViewHas('totalApprovedPosts', 0);
    }
}
