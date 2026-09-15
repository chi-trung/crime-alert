<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredCache extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Xóa các dòng cache database đã hết hạn mà DatabaseStore không tự quét';

    /**
     * Issue #299 (RL-4): with CACHE_STORE=database (the project default,
     * .env.example:40) every throttle bucket is a `cache` row, and the
     * IP-keyed GUEST lanes mint a fresh permanent row per source address —
     * Illuminate\Cache\DatabaseStore only deletes an expired row when the
     * SAME key is touched again, so a one-hit-per-IP probe leaves its rows
     * behind forever. Laravel 12 ships no prune command for the table
     * store (artisan list cache: clear/forget/prune-stale-tags/table only)
     * and the table has no session-style GC lottery. This is the janitor:
     * delete rows past expiry, mirroring DatabaseStore's own predicate.
     *
     * Semantics copied from vendor DatabaseStore:
     * - rows are only expired when expiration > 0 AND <= now: a zero (or
     *   negative) expiration is the "forever" encoding on older dumps, and
     *   Store::forever() itself writes now + 315360000, never <= 0;
     * - the value column holds serialized payload; deletion is the same
     *   hard delete DatabaseStore::forgetManyIfExpired performs.
     */
    public function handle(): int
    {
        $deleted = DB::table(config('cache.stores.database.table', 'cache'))
            ->where('expiration', '>', 0)
            ->where('expiration', '<=', time())
            ->delete();

        $this->info("Đã dọn {$deleted} dòng cache hết hạn.");

        return self::SUCCESS;
    }
}
