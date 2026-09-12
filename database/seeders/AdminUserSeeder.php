<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed a default administrator so admin areas are reachable on a fresh install.
     *
     * IMPORTANT: change the password immediately after the first login.
     * The credentials are only meant for local/staging environments.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@crime-alert.local');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => env('ADMIN_PASSWORD', 'ChangeMe!123'),
                'email_verified_at' => now(),
            ]
        );

        // isAdmin khong fillable (chong mass assignment) -> gan quyen explicit
        $admin->forceFill(['isAdmin' => true])->save();
    }
}
