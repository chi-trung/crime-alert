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
     */
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if ($password === null) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'ADMIN_PASSWORD must be set in production: the seeder fallback is public knowledge.'
                );
            }
            $this->command?->warn('AdminUserSeeder: using the public fallback password — never do this outside local/staging.');
            $password = 'ChangeMe!123';
        }

        $email = env('ADMIN_EMAIL', 'admin@crime-alert.local');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        // isAdmin khong fillable (chong mass assignment) -> gan quyen explicit
        $admin->forceFill(['isAdmin' => true])->save();
    }
}
