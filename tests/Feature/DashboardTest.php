<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_verified_user_sees_own_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('myLatest')
            ->assertViewHas('typePercents')
            ->assertViewHas('myExperience');

        // Admin-only aggregates must not leak into the regular-user payload.
        $this->actingAs($user)->get('/dashboard')->assertViewMissing('totalAlerts');
    }

    public function test_dashboard_payload_ships_no_dead_keys(): void
    {
        // Issue #71: these keys were computed (some by unbounded queries) and
        // shipped to a view that never read them. They — and the queries
        // behind them — must stay deleted.
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $dead = ['myTotal', 'myApproved', 'myAlerts'];
        foreach ($dead as $key) {
            $this->actingAs($user)->get('/dashboard')->assertViewMissing($key);
        }

        // latestAlerts/latestPending/pendingExperiences/latestPendingExperience
        // were admin-only; assert all four against the admin render.
        foreach (array_merge($dead, ['latestAlerts', 'latestPending', 'pendingExperiences', 'latestPendingExperience']) as $key) {
            $this->actingAs($admin)->get('/dashboard')->assertViewMissing($key);
        }
    }

    public function test_admin_sees_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalAlerts')
            ->assertViewHas('pendingAlerts')
            ->assertViewHas('typePercentsAdmin')
            ->assertViewHas('createdData')
            ->assertViewHas('approvedData');
    }

    public function test_admin_dashboard_reflects_real_counts(): void
    {
        $admin = User::factory()->admin()->create();
        Alert::create(['user_id' => $admin->id, 'title' => 'a', 'description' => 'd', 'type' => 'Trộm cắp', 'status' => 'approved']);
        Alert::create(['user_id' => $admin->id, 'title' => 'b', 'description' => 'd', 'type' => 'Khong ro', 'status' => 'pending']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('totalAlerts', 2);
        $response->assertViewHas('pendingAlerts', 1);
        $response->assertViewHas('approvedAlerts', 1);
    }

    public function test_user_dashboard_counts_every_post_once_approved_separately(): void
    {
        $user = User::factory()->create();
        // Fixed this-month timestamps, a minute apart: totalPosts filters on
        // month, and the myLatest assertion depends on which alert is newest —
        // both would break if the rows shared now()'s second or crossed a
        // boundary relative to the wall clock.
        $earlyMonth = now()->startOfMonth()->addDay();
        // forceCreate, not create: created_at is not in Alert::$fillable, so
        // mass assignment would drop it and both rows would share now()'s
        // second (undefined tie order).
        Alert::forceCreate(['user_id' => $user->id, 'title' => 'ok', 'description' => 'd', 'type' => 'Lừa đảo', 'status' => 'approved', 'created_at' => $earlyMonth]);
        Alert::forceCreate(['user_id' => $user->id, 'title' => 'pend', 'description' => 'd', 'status' => 'pending', 'created_at' => $earlyMonth->copy()->addMinute()]);
        Experience::create(['user_id' => $user->id, 'name' => 'Na', 'title' => 'e', 'content' => 'c', 'status' => 'approved']);

        $response = $this->actingAs($user)->get('/dashboard');

        // Issue #238: this test used to pin the asymmetry itself —
        // totalPosts=2 excluding the pending alert while an experience of any
        // status counted, "X/Y được duyệt" mixing two post-sets. Now one rule
        // for both types: every post once in the denominator, approved ones
        // in the numerator. 2 alerts + 1 experience; 1 approved alert +
        // 1 approved experience.
        $response->assertViewHas('totalPosts', 3);
        $response->assertViewHas('totalApprovedPosts', 2);
        // myLatest is the newest alert of any status, not the newest approved
        // one — the "pend" row was created after "ok".
        $response->assertViewHas('myLatest', fn ($alert) => $alert->title === 'pend');
    }

    public function test_dashboard_does_not_read_the_dead_queues_at_all(): void
    {
        // Issue #71: the admin dashboard used to pull the entire pending
        // experience queue, and the user dashboard the user's whole alert
        // history, to feed keys no view read. The surviving unbounded read is
        // the global approved-alert breakdown typeBreakdown needs; nothing
        // else may scan those tables without a LIMIT.
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        foreach (range(1, 30) as $i) {
            Experience::create(['user_id' => $user->id, 'name' => 'N', 'title' => "E{$i}", 'content' => 'c', 'status' => 'pending']);
            Alert::create(['user_id' => $user->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'pending']);
        }

        $queries = [];
        foreach ([$admin, $user] as $viewer) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($viewer)->get('/dashboard')->assertOk();
            foreach (DB::getQueryLog() as $entry) {
                $queries[] = [strtolower($entry['query']), $entry['bindings']];
            }
        }
        DB::disableQueryLog();

        foreach ($queries as [$q, $bindings]) {
            $strs = array_map('strval', $bindings);
            // sqlite quotes identifiers with "", mysql with ``, so match the
            // table name tolerantly.
            $readsAlerts = (bool) preg_match('/\bfrom\s+["`]?alerts["`]?/i', $q);
            $readsExperiences = (bool) preg_match('/\bfrom\s+["`]?experiences["`]?/i', $q);

            // Pending experiences must never be fetched as a list anymore:
            // neither for the deleted queue nor per-row. COUNT(*) over the
            // pending queue is still legitimate, so only a select * of rows
            // counts as a regression.
            $listsPendingExperiences = $readsExperiences
                && str_starts_with($q, 'select *')
                && in_array('pending', $strs, true);
            $this->assertFalse($listsPendingExperiences, "Dashboard still lists pending experiences: {$q}");

            // The user's full alert history was loaded to feed the deleted
            // myAlerts key. The surviving user-scoped reads are $myLatest
            // (LIMIT 1) and this-month totals (status='approved' bound). A
            // user-scoped select * with no LIMIT and no status binding is the
            // deleted whole-history scan returning. Keyed on bindings, not
            // SQL dialect, so it holds on both sqlite and mysql.
            $scansWholeHistory = $readsAlerts
                && str_starts_with($q, 'select *')
                && in_array((string) $user->id, $strs, true)
                && ! str_contains($q, 'limit')
                && ! array_intersect($strs, ['approved', 'pending', 'rejected']);
            $this->assertFalse($scansWholeHistory, "Dashboard still scans the whole alert history: {$q}");
        }
    }

    public function test_type_breakdown_reads_only_the_type_column(): void
    {
        // Issue #83: the pie chart's typeBreakdown() reads exactly one
        // attribute per row, so the global approved-alerts read must select
        // only `type` — hydrating full Alert models (description TEXT
        // included) for every approved alert on every render is waste the
        // DB removes for free.
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            Alert::create(['user_id' => $user->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'approved']);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $narrowed = 0;
        foreach ($log as $entry) {
            $q = strtolower($entry['query']);
            $strs = array_map('strval', $entry['bindings']);
            $readsAlerts = (bool) preg_match('/\bfrom\s+["`]?alerts["`]?/i', $q);
            if (! $readsAlerts) {
                continue;
            }
            // The breakdown read is the global one: 'approved' bound, no
            // user_id bound, no LIMIT (the pie must see every row). It must
            // no longer ship full rows. The $myAlertsThisMonth read is also
            // LIMIT-less but always binds a user_id, and latestAlert/myLatest
            // carry a LIMIT, so neither trips this.
            $isBreakdownScan = str_starts_with($q, 'select *')
                && in_array('approved', $strs, true)
                && ! str_contains($q, 'limit')
                && ! in_array((string) $user->id, $strs, true);
            $this->assertFalse($isBreakdownScan, "typeBreakdown still hydrates full models: {$q}");
            if (preg_match('/\bselect\s+["`]?type["`]?(\s*,\s*["`]?type["`]?)*\s+from\s+["`]?alerts["`]?/i', $q)) {
                $narrowed++;
            }
        }
        // Positive control: both dashboards must still run the (now
        // type-only) breakdown read — so the negatives above can't pass by
        // the chart's query being deleted outright.
        $this->assertGreaterThanOrEqual(2, $narrowed, 'no type-only alerts read ran on the two dashboard renders');
    }

    public function test_empty_dashboard_shows_no_phantom_type_percentage(): void
    {
        // Issue #65: with zero approved alerts the old remainder scheme put
        // 100% into the "Khác" bucket, so the pie cards claimed all crime was
        // "other" on a fresh install. The payload must be all zeros.
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('typePercents', [
                'Cướp giật' => 0,
                'Trộm cắp' => 0,
                'Lừa đảo' => 0,
                'Bạo lực' => 0,
                'Khác' => 0,
            ]);
        // (The cards render these values verbatim; a raw `assertDontSee('100%')`
        // can't work here because the stylesheet legitimately contains
        // `height: 100%`.)
    }
}
