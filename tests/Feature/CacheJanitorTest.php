<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #299 (RL-4): CACHE_STORE=database is the default (.env.example:40,
 * config/cache.php:18), so every throttle bucket — including the IP-keyed
 * GUEST lanes (ThrottleRequests::resolveRequestSignature keys guests by
 * route domain + '|' + $request->ip()) — is a row in the `cache` table.
 * Illuminate\Cache\DatabaseStore only removes an expired row when the SAME
 * key is touched again; nothing ever scans expired rows, Laravel 12 ships
 * no cache:prune-style command for the table store (verified on this
 * install: artisan list cache shows only clear/forget/prune-stale-tags/
 * table), and routes/console.php scheduled only the two crawls. An
 * attacker rotating real source IPs (X-Forwarded-For does not work —
 * TrustProxies is installed but $proxies is null, so $request->ip() is
 * REMOTE_ADDR) hits a guest-laned POST once per IP and permanently grows
 * the cache table: a slow storage-exhaustion primitive with zero
 * application-side bound. Sessions contrast: the database session driver
 * carries the [2,100] GC lottery; the cache table had no equivalent.
 */
class CacheJanitorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the store the way DatabaseStore itself writes rows (the suite
     * default is CACHE_STORE=array, hence the explicit database store —
     * the janitor reads the table, so this is what production's
     * CACHE_STORE=database looks like). Two aged-out guest buckets, one
     * live bucket, one forever row (DatabaseStore::forever is
     * put(..., 315360000), a year ahead — the janitor must NOT touch it).
     */
    private function seedBuckets(): void
    {
        $store = Cache::store('database');
        $store->add('lane-203.0.113.1', 1, 60);
        $store->add('lane-203.0.113.2', 1, 60);
        $store->add('lane-live', 1, 600);
        $store->forever('lane-forever', 1);

        // Age the two "rotating IP" buckets past their TTL without
        // deleting them — exactly the state an old guest hit sits in once
        // its decay window passes.
        DB::table('cache')->whereIn('key', [
            config('cache.prefix').'lane-203.0.113.1',
            config('cache.prefix').'lane-203.0.113.2',
        ])->update(['expiration' => time() - 10]);
    }

    public function test_expired_cache_rows_are_swept_by_the_prune_command(): void
    {
        $this->seedBuckets();

        // Pre-fix: `cache:prune-expired` does not exist -> artisan exits 1
        // ("Command not found") and the expired rows sit forever.
        $this->artisan('cache:prune-expired')->assertSuccessful();

        $this->assertSame(
            0,
            DB::table('cache')->where('expiration', '>', 0)->where('expiration', '<=', time())->count(),
            'expired rows survived the janitor run'
        );
    }

    public function test_prune_keeps_live_buckets_and_forever_rows(): void
    {
        $this->seedBuckets();

        $this->artisan('cache:prune-expired')->assertSuccessful();

        $store = Cache::store('database');

        // The two expired guest lanes are gone...
        $this->assertNull($store->get('lane-203.0.113.1'));
        $this->assertNull($store->get('lane-203.0.113.2'));
        // ...the live bucket keeps its value (a within-window counter must
        // survive, or throttling would silently reset itself)...
        $this->assertSame(1, $store->get('lane-live'));
        // ...and the forever row (expiration a year ahead) is untouched.
        $this->assertSame(1, $store->get('lane-forever'));
    }

    public function test_ip_keyed_guest_lane_rows_accumulate_without_the_janitor(): void
    {
        // The growth half of the claim, pinned end-to-end through the real
        // middleware: 50 distinct source IPs POSTing to a guest-laned route
        // mint 50 permanent cache rows (one bucket per IP), and DatabaseStore
        // never revisits a key whose window has closed.
        config(['cache.default' => 'database']);
        Cache::forgetDriver('array');
        Cache::forgetDriver('database');

        $before = DB::table('cache')->count();
        for ($i = 1; $i <= 50; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->post(route('password.email'), ['email' => 'nobody@example.org']);
        }
        $grown = DB::table('cache')->count() - $before;

        // 50 IP-keyed buckets x 2 rows each — the counter AND the
        // ':timer' decay row (Cache\RateLimiter::increment writes both,
        // sha1-keyed on domain|ip) — so the growth per rotating hit is
        // permanent, unbounded rows, counted here rather than
        // pattern-matched because the keys are hashed.
        $this->assertSame(100, $grown);

        // Once a bucket's decay window passes, NOTHING removes it: the
        // row only dies when the SAME key is touched again, and a rotating-
        // IP attacker never revisits an address. Age every row out and
        // confirm the backlog still stands before the janitor exists...
        DB::table('cache')->where('expiration', '>', 0)->update(['expiration' => time() - 1]);
        $this->assertSame(100, DB::table('cache')->where('expiration', '<=', time())->count());

        // ...then the scheduled janitor sweeps exactly that backlog.
        $this->artisan('cache:prune-expired')->assertSuccessful();
        $this->assertSame(0, DB::table('cache')->count());
    }

    public function test_the_cache_janitor_is_scheduled_hourly(): void
    {
        $events = collect(app(Schedule::class)->events());

        $janitor = $events->first(fn ($e) => str_contains((string) $e->command, 'cache:prune-expired'));

        $this->assertNotNull($janitor, 'cache:prune-expired is not scheduled — expired guest-lane rows accumulate forever');

        // Hourly cadence (the claim being pinned): runs every hour with no
        // day-of-week/day-of-month restriction, so the worst-case backlog
        // is one hour of new IP-keyed rows.
        $this->assertSame('0 * * * *', $janitor->expression);
    }
}
