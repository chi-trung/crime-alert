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
