<?php

namespace Tests\Unit;

use App\Services\DashboardStatsService;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
    private function service(): DashboardStatsService
    {
        return new DashboardStatsService;
    }

    public function test_type_breakdown_buckets_unknown_types_into_khac(): void
    {
        $alerts = collect([
            (object) ['type' => 'Trộm cắp'],
            (object) ['type' => 'Trộm cắp'],
            (object) ['type' => 'Vu khac'],
            (object) ['type' => null],
        ]);

        [$total, $percents] = $this->service()->typeBreakdown($alerts);

        $this->assertSame(4, $total);
        $this->assertSame(100, array_sum($percents));
        $this->assertArrayHasKey('Khác', $percents);
    }

    public function test_type_breakdown_empty_collection_yields_all_zeros(): void
    {
        // Issue #65: the old remainder-to-last-bucket scheme made the empty
        // case sum to 100 by handing "Khác" 100 - 0 = 100%, so a dashboard
        // with zero approved alerts rendered "Khác: 100%".
        [$total, $percents] = $this->service()->typeBreakdown(collect());

        $this->assertSame(0, $total);
        $this->assertSame(['Cướp giật', 'Trộm cắp', 'Lừa đảo', 'Bạo lực', 'Khác'], array_keys($percents));
        $this->assertSame([0, 0, 0, 0, 0], array_values($percents));
    }

    public function test_percents_are_never_negative_when_rounding_overruns(): void
    {
        // Issue #65: 2/2/2/1/0 rounded up to 29+29+29+14 = 101 before the
        // last bucket absorbed -1%, which the view printed verbatim
        // (`?? 0` catches null, not negatives).
        $alerts = collect(array_merge(
            array_fill(0, 2, (object) ['type' => 'Cướp giật']),
            array_fill(0, 2, (object) ['type' => 'Trộm cắp']),
            array_fill(0, 2, (object) ['type' => 'Lừa đảo']),
            array_fill(0, 1, (object) ['type' => 'Bạo lực']),
        ));

        [$total, $percents] = $this->service()->typeBreakdown($alerts);

        $this->assertSame(7, $total);
        $this->assertSame(100, array_sum($percents));
        foreach ($percents as $type => $percent) {
            $this->assertGreaterThanOrEqual(0, $percent, $type);
        }
        $this->assertSame(0, $percents['Khác']);
    }

    public function test_percents_sum_to_exactly_100_despite_rounding(): void
    {
        $alerts = collect([
            (object) ['type' => 'Trộm cắp'],
            (object) ['type' => 'Lừa đảo'],
            (object) ['type' => 'Bạo lực'],
        ]);

        [, $percents] = $this->service()->typeBreakdown($alerts);

        $this->assertSame(100, array_sum($percents));
    }

    public function test_percent_change_handles_zero_baseline(): void
    {
        $svc = $this->service();

        $this->assertSame(100, $svc->percentChange(5, 0));
        $this->assertSame(0, $svc->percentChange(0, 0));
        $this->assertSame(50, $svc->percentChange(15, 10));
        $this->assertSame(-50, $svc->percentChange(5, 10));
    }
}
