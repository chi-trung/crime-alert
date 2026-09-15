<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Issue #277: the demo user used to be created unconditionally —
     * User::factory()->create(['email' => 'test@example.com', ...]) — and
     * the factory ships email_verified_at=now() and Hash::make('password')
     * (applied via Model::unguarded, so the non-fillable verified
     * timestamp lands too). "migrate --seed runs in essentially every
     * deploy recipe" is this repo's own documented doctrine
     * (config/admin.php), so a production seed planted a fully-verified
     * account whose credentials are printed in a public repository:
     * anonymous POST /login → write access (alerts, comments, likes,
     * support threads) under "Test User" on a crime-reporting board. The
     * sibling AdminUserSeeder throws in production for exactly this
     * "public knowledge" asymmetry (#45) — and the demo row was created
     * BEFORE that throw, surviving even an aborted seed. A demo user is
     * not a misconfiguration an operator can fix by setting an env var
     * (there is nothing to set), it is simply not a production artifact:
     * skip it with a warning when app()->isProduction() instead of
     * failing the deploy. CI (.env.example APP_ENV=local) and tests
     * (phpunit.xml testing) keep seeding it unchanged.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DatabaseSeeder: skipping the demo user (test@example.com) on production — its credentials are public knowledge (issue #277).');
        } else {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call(AdminUserSeeder::class);
    }
}
