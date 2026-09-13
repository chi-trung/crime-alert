<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => config('admin.name'),
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        // isAdmin khong fillable (chong mass assignment) -> gan quyen explicit
        $admin->forceFill(['isAdmin' => true])->save();
    }
}
