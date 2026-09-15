<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Crawl định kỳ (issue #16). withoutOverlapping ensures a slow crawl cannot
// stack up with the next tick; the crawler commands tolerate network errors
// themselves, so a failed run just waits for the next slot.
Schedule::command('crawl:news')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('crawl:wanted-list')->hourly()->withoutOverlapping();

// Issue #299 (RL-4): with CACHE_STORE=database the default, every throttle
// bucket is a `cache` row and the IP-keyed guest lanes (login/register/
// forgot/reset/verify/pw-confirm) mint one permanent row per source
// address — DatabaseStore only cleans an expired row when the SAME key is
// touched again, and a rotating-IP attacker never revisits one. Laravel
// ships no prune command for the table store, so the table had no session-
// style GC equivalent. Hourly cap on the backlog; the delete is idempotent,
// so withoutOverlapping is unnecessary.
Schedule::command('cache:prune-expired')->hourly();
