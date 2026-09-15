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
        } elseif (User::where('email', 'test@example.com')->exists()) {
            // Issue #313: re-seed is a no-op here, not a unique-violation
            // abort. users.email is UNIQUE, so the unconditional create threw
            // QueryException 23000 out of run() on every second `db:seed` /
            // `migrate --seed`, exiting 1 BEFORE the AdminUserSeeder call
            // below — the one component that IS idempotent (#273's branch
            // rotates an existing admin's password, nulls its remember token
            // and sweeps its sessions). A re-seed after a forgotten
            // ADMIN_PASSWORD rotation therefore did nothing but fail. An
            // existing demo row is exactly what a re-seed should leave alone:
            // rotating demo state was never this method's job, and a foreign
            // row squatting the address (public emails can be registered —
            // #273's premise, demo flavor) must likewise not be overwritten
            // by a seed; skipping leaves it owned by whoever registered it.
            $this->command?->line('DatabaseSeeder: demo user (test@example.com) already present — leaving it untouched (issue #313).');
        } else {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        $this->call(AdminUserSeeder::class);
    }
}
