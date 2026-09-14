<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #226: percentChange() returns a SIGNED month-over-month delta, but
 * the dashboard tiles hardcoded badge color and arrow direction per tile —
 * Chờ duyệt always down+red (so a +100% pending surge pointed DOWN), the
 * other five always up+green (so a -50% rejected drop pointed UP), and
 * 'Tỷ lệ duyệt' printed a fabricated static '3%' no data could move. The
 * tiles now render through partials/stat-badge, which derives the glyph
 * from the delta's sign plus per-tile semantics ($goodWhenUp), with a
 * neutral grey state at zero — and the rate tile's footnote is the real
 * rateChange() from DashboardStatsService.
 */
class DashboardBadgeSignTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdminDashboard(): User
    {
        $admin = User::factory()->admin()->create();

        $lastMonth = now()->startOfMonth()->subMonth()->addDay();
        $thisMonth = now()->startOfMonth()->addDay();

        // Last month: 1 pending, 2 approved, 2 rejected (5 total, rate 40).
        foreach ([['pending', 'p1'], ['approved', 'a1'], ['approved', 'a2'], ['rejected', 'r1'], ['rejected', 'r2']] as [$status, $title]) {
            Alert::forceCreate([
                'user_id' => $admin->id,
                'title' => $title,
                'description' => 'd',
                'status' => $status,
                'created_at' => $lastMonth,
            ]);
        }
        // This month: 2 pending, 1 approved, 1 rejected (4 total, rate 25).
        // pendingPercent +100, approvedPercent -50, rejectedPercent -50,
        // totalAlertsPercent -20, approvalRateChange 25-40 = -15 points.
        foreach ([['pending', 'p2'], ['pending', 'p3'], ['approved', 'a3'], ['rejected', 'r3']] as [$status, $title]) {
            Alert::forceCreate([
                'user_id' => $admin->id,
                'title' => $title,
                'description' => 'd',
                'status' => $status,
                'created_at' => $thisMonth,
            ]);
        }

        return $admin;
    }

    public function test_a_rising_pending_queue_points_up_not_down(): void
    {
        $admin = $this->seedAdminDashboard();

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        // +100 pending growth: bad-news tile (goodWhenUp=false), so the
        // badge must read red/UP — the old markup hardcoded down/RED, and
        // before that a +100% surge sat under an arrow pointing down.
        $this->assertMatchesRegularExpression(
            '/bg-danger bg-opacity-10 text-danger">\s*<i class="fas fa-arrow-up me-1"><\/i> 100%/',
            $html,
            'a rising pending queue must render an UP arrow'
        );
        $this->assertStringNotContainsString('fa-arrow-down me-1"></i> 100%', $html);
    }

    public function test_a_falling_rejected_count_points_down_not_up(): void
    {
        $admin = $this->seedAdminDashboard();

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        // -50% rejected: fewer rejections is good news (goodWhenUp=false),
        // so down+GREEN. The old markup printed '-50%' under a hardcoded
        // up+green arrow — the sign and the glyph disagreed.
        $this->assertMatchesRegularExpression(
            '/bg-success bg-opacity-10 text-success">\s*<i class="fas fa-arrow-down me-1"><\/i> -50%/',
            $html,
            'a falling rejected count must render a DOWN arrow'
        );
        $this->assertStringNotContainsString('fa-arrow-up me-1"></i> -50%', $html);
    }

    public function test_the_rate_tile_prints_the_real_delta_not_the_static_three_percent(): void
    {
        $admin = $this->seedAdminDashboard();

        $this->actingAs($admin)->get('/dashboard')
            // The payload key is gone-offabrication: 25% rate now vs 40%
            // last month = -15 percentage points.
            ->assertViewHas('approvalRateChange', -15);

        $html = $this->actingAs($admin)->get('/dashboard')->getContent();
        // The old tile's literal markup: a fake '3%' that no query could
        // influence. It must be gone, and the real value must render with
        // the matching down/red badge (rate is a goodWhenUp metric).
        $this->assertStringNotContainsString('fa-arrow-up me-1"></i> 3%', $html);
        $this->assertMatchesRegularExpression(
            '/bg-danger bg-opacity-10 text-danger">\s*<i class="fas fa-arrow-down me-1"><\/i> -15%/',
            $html,
            'the approval-rate tile must render the real month-over-month change'
        );
    }

    public function test_a_zero_delta_renders_neutral_grey_without_an_arrow(): void
    {
        $admin = User::factory()->admin()->create();
        $month = now()->startOfMonth()->subMonth()->addDay();
        // Identical pending volume both months -> percentChange 0.
        foreach ([1, 2] as $i) {
            Alert::forceCreate([
                'user_id' => $admin->id, 'title' => "lp$i", 'description' => 'd',
                'status' => 'pending', 'created_at' => $month,
            ]);
            Alert::forceCreate([
                'user_id' => $admin->id, 'title' => "tp$i", 'description' => 'd',
                'status' => 'pending', 'created_at' => $month->copy()->addMonth(),
            ]);
        }

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/bg-secondary bg-opacity-10 text-secondary">\s*<i class="fas fa-minus me-1"><\/i> 0%/',
            $html,
            'a flat metric must render the neutral badge'
        );
    }

    public function test_rate_change_guards_undefined_rates(): void
    {
        $svc = app(DashboardStatsService::class);

        $this->assertSame(-15, $svc->rateChange(1, 4, 2, 5));
        $this->assertSame(0, $svc->rateChange(0, 0, 2, 5));   // this month: no rate
        $this->assertSame(0, $svc->rateChange(2, 5, 0, 0));   // last month: no rate
        $this->assertSame(0, $svc->rateChange(0, 0, 0, 0));   // neither
        $this->assertSame(10, $svc->rateChange(6, 10, 5, 10)); // +10 percentage points
    }
}
