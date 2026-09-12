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

    public function test_type_breakdown_empty_collection_yields_100_khac(): void
    {
        [$total, $percents] = $this->service()->typeBreakdown(collect());

        $this->assertSame(0, $total);
        $this->assertSame(100, array_sum($percents));
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
