<?php

// Issue #183: these values used to be read via env() directly inside
// AdminUserSeeder. Laravel skips .env loading entirely once configuration
// is cached (LoadEnvironmentVariables early-returns on
// configurationIsCached()), and `php artisan config:cache` runs before
// `migrate --seed` in essentially every deploy recipe — so every
// env('ADMIN_*') read returned null EVEN THOUGH the variables were set in
// .env: production got a false "ADMIN_PASSWORD must be set" deploy
// failure, and non-production silently seeded the public fallback admin
// documented in this repo's README. config/ files are the legal home for
// env(): they are evaluated AT config:cache time, so the real values bake
// into the cache and the seeder reads them through config('admin.*').
return [

    /*
    |--------------------------------------------------------------------------
    | Default Administrator
    |--------------------------------------------------------------------------
    |
    | Credentials AdminUserSeeder provisions so admin areas are reachable on
    | a fresh install. 'password' has no default on purpose: null means
    | "unset at cache time", which #45's guard turns into a hard throw in
    | production (and a warn + public fallback elsewhere).
    |
    */

    'email' => env('ADMIN_EMAIL', 'admin@crime-alert.local'),

    'password' => env('ADMIN_PASSWORD'),

    'name' => env('ADMIN_NAME', 'Administrator'),

];
