<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default administrator so admin areas are reachable on a fresh install.
     *
     * IMPORTANT: change the password immediately after the first login.
     * The credentials are only meant for local/staging environments — in
     * production ADMIN_PASSWORD is mandatory (issue #45): the fallback
     * value lives in this public repo, so a forgotten env var would
     * otherwise hand anyone who reads the seeder a working admin login.
     *
     * Issue #183: values arrive through config('admin.*'), never env().
     * Laravel skips .env loading once configuration is cached, and every
     * deploy recipe runs config:cache before migrate --seed — so env()
     * here used to read null for a variable that WAS set: a false
     * production throw, and a public-fallback admin seeded elsewhere.
     * The null check below now correctly means "unset at cache time".
     */
    public function run(): void
    {
        $password = config('admin.password');

        // Empty-string counts as unset: a deploy that sets ADMIN_PASSWORD=""
        // is just as backdoored as one that omits it.
        if ($password === null || $password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD must be set in production: the seeder fallback is public knowledge.'
                );
            }
            $this->command?->warn('AdminUserSeeder: using the public fallback password — never do this outside local/staging.');
            $password = 'ChangeMe!123';
        }

        $email = config('admin.email');

        // Issue #273: this used to end in updateOrCreate(['email' => $email]),
        // which — like firstOrCreate — matches ANY pre-existing row before it
        // inserts. The email ships a public default ('admin@crime-alert.local',
        // config/admin.php) that passes every registration rule: filter_var
        // accepts .local, no mailbox behind it has to exist, and `unique:User`
        // only refuses an owner. So an attacker registers that address first,
        // and the next `migrate --seed` — which the repo's own deploy notes
        // call essentially every recipe — UPDATEs the attacker's row
        // (name/password/verified_at) and forceFill(isAdmin=true) promoted
        // it: privilege escalation through the seeder's own idempotence. The
        // guard below uses isAdmin itself as the provenance marker #273
        // considered adding a column for: a registration cannot set it
        // (not fillable, defaults 0 — RegisteredUserController's #205 pin),
        // and only this method ever sets it true outside manual DBA work.
        // A taken address therefore always reads isAdmin=false and dies as
        // a RuntimeException in every environment — a config mistake or an
        // active takeover attempt is not something to seed through silently.
        $admin = User::where('email', $email)->first();

        if ($admin && ! $admin->isAdmin) {
            throw new RuntimeException(
                "Refusing to seed: {$email} is already owned by a non-admin account (id {$admin->id}). "
                .'This is either a misconfiguration or the public default admin email was registered '
                .'by someone else — see issue #273. Set ADMIN_EMAIL to an address no one owns, or '
                .'remove/demote the existing account, then re-run the seed.'
            );
        }

        if (! $admin) {
            // Fresh install: the insert sets only fillable attributes —
            // email_verified_at is NOT fillable (User's mass-assignment
            // list), so it rides the forceFill below with isAdmin, or the
            // "admin" ships unverified and every gated route locks it out.
            $admin = User::create([
                'name' => config('admin.name'),
                'email' => $email,
                'password' => $password, // 'hashed' cast hashes it
            ]);
            $admin->forceFill([
                'isAdmin' => true,
                'email_verified_at' => now(),
            ])->save();
        } else {
            // Legit re-seed of the admin this method provisioned (a moved
            // host, a forgotten password): reset the credentials, and —
            // per #27/#123/#253's doctrine that rotating credentials ends
            // the old ones' power — null the remember token so a stolen
            // remember-me cookie stops restoring the admin, and sweep the
            // database sessions. The seeder has no "current session" of
            // its own, so every row for this user goes; on file/array
            // drivers the table is unused and these are no-ops (guarded
            // because a fresh checkout may not have migrated it yet — it
            // ships with the framework's users-table migration).
            $admin->forceFill([
                'name' => config('admin.name'),
                'password' => $password,
                'email_verified_at' => now(),
                'remember_token' => null,
            ])->save();

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $admin->id)->delete();
            }
        }
    }
}
