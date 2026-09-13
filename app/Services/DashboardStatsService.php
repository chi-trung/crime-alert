<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\News;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\WantedPerson;
use Illuminate\Support\Collection;

/**
 * Aggregates the data previously computed inline in the 220-line
 * /dashboard route closure (issue #7). Issue #71 pruned the payload:
 * keys no view ever read (latestAlerts, latestPending,
 * pendingExperiences, latestPendingExperience, myTotal, myApproved,
 * myAlerts) are gone along with the queries backing them, so what is
 * returned here is exactly what dashboard.blade.php consumes.
 */
class DashboardStatsService
{
    /**
     * Canonical alert categories; anything else rolls up into "Khác".
     *
     * @var list<string>
     */
    public const ALERT_TYPES = ['Cướp giật', 'Trộm cắp', 'Lừa đảo', 'Bạo lực'];

    /**
     * Admin dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function forAdmin(): array
    {
        // Issue #83: get(['type']) — typeBreakdown reads exactly this one
        // column, so hydrating full Alert models (description TEXT, Carbon
        // timestamps, per-model bootstrapping) for every approved alert on
        // every dashboard render was pure waste.
        [, $typePercents] = $this->typeBreakdown(
            Alert::where('status', 'approved')->get(['type'])
        );

        $currentYear = now()->year;
        $currentMonth = now()->month;
        $lastMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $lastMonthYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

        $totals = [
            'total' => [
                $this->countAlertsInMonth($currentYear, $currentMonth),
                $this->countAlertsInMonth($lastMonthYear, $lastMonth),
            ],
            'pending' => [
                $this->countAlertsInMonth($currentYear, $currentMonth, 'pending'),
                $this->countAlertsInMonth($lastMonthYear, $lastMonth, 'pending'),
            ],
            'approved' => [
                $this->countAlertsInMonth($currentYear, $currentMonth, 'approved'),
                $this->countAlertsInMonth($lastMonthYear, $lastMonth, 'approved'),
            ],
            'rejected' => [
                $this->countAlertsInMonth($currentYear, $currentMonth, 'rejected'),
                $this->countAlertsInMonth($lastMonthYear, $lastMonth, 'rejected'),
            ],
            'users' => [
                $this->countUsersInMonth($currentYear, $currentMonth),
                $this->countUsersInMonth($lastMonthYear, $lastMonth),
            ],
        ];

        [$alertsCreated, $alertsApproved] = $this->monthlySeries($currentYear);

        return array_merge($this->sharedLists(), [
            'totalAlerts' => Alert::count(),
            'pendingAlerts' => Alert::where('status', 'pending')->count(),
            'approvedAlerts' => Alert::where('status', 'approved')->count(),
            'rejectedAlerts' => Alert::where('status', 'rejected')->count(),
            'totalUsers' => User::count(),
            // Issue #87: created_at is second-resolution, so same-second rows
            // left even the single-row "latest" pick undefined — id DESC
            // resolves the tie toward the newest row (#81's idiom).
            'latestAlert' => Alert::orderByDesc('created_at')->orderByDesc('id')->first(),
            'createdData' => $alertsCreated,
            'approvedData' => $alertsApproved,
            'totalAlertsPercent' => $this->percentChange(...$totals['total']),
            'pendingPercent' => $this->percentChange(...$totals['pending']),
            'approvedPercent' => $this->percentChange(...$totals['approved']),
            'rejectedPercent' => $this->percentChange(...$totals['rejected']),
            'totalUsersPercent' => $this->percentChange(...$totals['users']),
            'myExperience' => null,
            'latestExperience' => Experience::orderByDesc('created_at')->orderByDesc('id')->first(),
            'typePercents' => $typePercents,
            'typePercentsAdmin' => $typePercents,
            'latestSupportRequest' => SupportRequest::with('user')->latest()->orderByDesc('id')->first(),
        ]);
    }

    /**
     * Regular user dashboard payload.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        // User's alerts this month (approved only) and experiences this month.
        $myAlertsThisMonth = Alert::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->orderByDesc('created_at')
            ->get();
        $myExperiencesThisMonth = Experience::where('user_id', $user->id)
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->orderByDesc('created_at')
            ->get();

        // Issue #71: the user's own typeBreakdown call and its $myTotal were
        // dead — no view reads myTotal/myApproved, so they are gone along with
        // that call. What remains is the breakdown the pie chart actually
        // renders: the GLOBAL approved-alert one, by pre-existing design, not
        // the user's own mix.
        // Issue #83: get(['type']) — see forAdmin; only type is read.
        [, $globalTypePercents] = $this->typeBreakdown(
            Alert::where('status', 'approved')->get(['type'])
        );

        // Issue #87: id tiebreak on the two single-row picks below — same
        // second-resolution created_at ambiguity as the rest of this file.
        $myExperience = Experience::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        // Only the newest row is rendered ($myLatest); the old unbounded
        // $myAlerts collection (issue #67's read-side class) existed solely
        // to call ->first() on it.
        $myLatest = Alert::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->first();

        return array_merge($this->sharedLists(), [
            'myLatest' => $myLatest,
            'typePercents' => $globalTypePercents,
            'monthLabel' => now()->format('m/Y'),
            'myExperience' => $myExperience,
            'myExperiencesThisMonth' => $myExperiencesThisMonth,
            'totalPosts' => $myAlertsThisMonth->count() + $myExperiencesThisMonth->count(),
            'totalApprovedPosts' => $myAlertsThisMonth->count() + $myExperiencesThisMonth->where('status', 'approved')->count(),
            'latestSupportRequest' => SupportRequest::where('user_id', $user->id)->latest()->orderByDesc('id')->first(),
        ]);
    }

    /**
     * Count + percentage breakdown by canonical alert type. Percents always
     * sum to exactly 100 and are never negative (largest-remainder
     * apportionment); an empty input yields all zeros.
     *
     * @return array{0: int, 1: array<string, int>} [total, percents]
     */
    public function typeBreakdown(Collection $alerts): array
    {
        $counts = array_fill_keys(self::ALERT_TYPES, 0);
        $counts['Khác'] = 0;

        foreach ($alerts as $alert) {
            $type = trim($alert->type ?? '');
            $counts[in_array($type, self::ALERT_TYPES, true) ? $type : 'Khác']++;
        }

        $total = array_sum($counts);

        // Issue #65: the old code rounded every bucket but the last, which
        // absorbed the rounding remainder. That broke both ends of the
        // range: with zero alerts the sum of the rounded buckets is 0, so
        // "Khác" received 100 - 0 = 100% and an empty dashboard claimed all
        // crime was "other"; and with a split like 2/2/2/1 the first four
        // round up to 29+29+29+14 = 101, so "Khác" went to -1%, which the
        // view prints verbatim (`?? 0` only catches null, not negatives).
        // Largest-remainder apportionment instead: floor each share, then
        // hand the leftover +1 units to the buckets with the biggest
        // fractional parts. Non-negative by construction, sums to exactly
        // 100 whenever there is data, and 0 total => all buckets 0.
        $percents = [];
        if ($total === 0) {
            foreach ($counts as $type => $count) {
                $percents[$type] = 0;
            }

            return [$total, $percents];
        }

        $exact = [];
        $used = 0;
        foreach ($counts as $type => $count) {
            $value = $count * 100 / $total;
            $floor = (int) floor($value);
            $percents[$type] = $floor;
            $exact[$type] = $value - $floor;
            $used += $floor;
        }
        $leftover = 100 - $used;
        if ($leftover > 0) {
            // uasort is stable since PHP 8.0, so equal fractional parts keep
            // the bucket declaration order — the +1 units never shuffle
            // between page loads.
            uasort($exact, fn ($a, $b) => $b <=> $a);
            foreach (array_slice(array_keys($exact), 0, $leftover) as $type) {
                $percents[$type]++;
            }
        }

        return [$total, $percents];
    }

    /**
     * Percentage change month-over-month; a zero baseline with new volume
     * reads as +100.
     */
    public function percentChange(int|float $current, int|float $last): int
    {
        if ($last == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $last) / $last) * 100);
    }

    /**
     * Blocks shared by both dashboard roles.
     *
     * @return array<string, mixed>
     */
    private function sharedLists(): array
    {
        return [
            'latestNews' => News::orderByDesc('published_at')->orderByDesc('id')->take(3)->get(),
            'hotWanted' => WantedPerson::orderByDesc('id')->take(3)->get(),
            // Issue #87: the three-way sorts below end in created_at, which
            // is second-resolution — rows from one burst are tied and LIMIT
            // 3/1 would otherwise decide membership by scan order.
            'topExperiences' => Experience::where('status', 'approved')
                ->withCount('comments')
                ->orderByDesc('comments_count')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take(3)
                ->get(),
            'topAlerts' => Alert::where('status', 'approved')
                ->withCount('comments')
                ->orderByDesc('comments_count')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take(3)
                ->get(),
        ];
    }

    private function countAlertsInMonth(int $year, int $month, ?string $status = null): int
    {
        return Alert::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
    }

    private function countUsersInMonth(int $year, int $month): int
    {
        return User::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();
    }

    /**
     * Created/approved alert counts per month of the given year, padded to
     * 12 slots for the chart. Grouped in PHP — MONTH() is MySQL-only and
     * the original closure was the one latent bug this refactor exposed
     * (admin dashboard crashed on SQLite).
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    private function monthlySeries(int $year): array
    {
        $months = Alert::query()
            ->whereYear('created_at', $year)
            ->pluck('created_at');

        $created = array_fill(1, 12, 0);
        $approved = array_fill(1, 12, 0);
        foreach ($months as $createdAt) {
            $month = (int) $createdAt->format('n');
            $created[$month]++;
        }
        $approvedMonths = Alert::query()
            ->where('status', 'approved')
            ->whereYear('created_at', $year)
            ->pluck('created_at');
        foreach ($approvedMonths as $createdAt) {
            $approved[(int) $createdAt->format('n')]++;
        }

        return [array_values($created), array_values($approved)];
    }
}
