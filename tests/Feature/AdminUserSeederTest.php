<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Issue #45: the seeder's fallback password lives in a public repo, so in
 * production it must be impossible to seed the default admin without an
 * explicit ADMIN_PASSWORD.
 */
class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function inEnvironment(string $env): void
    {
        // Application::environment() serves $app['env']; overriding the
        // container value is the supported way to flip it mid-test.
        $this->app['env'] = $env;
    }

    private function runSeeder(): void
    {
        // TestCase::seed() can't pass options, and db:seed in production
        // pops an interactive confirm (fatal under the mocked console).
        // --force is what deploy scripts use — exactly why the guard must
        // live in the seeder, not the command.
        Artisan::call('db:seed', [
            '--class' => AdminUserSeeder::class,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        parent::tearDown();
    }

    public function test_production_refuses_to_seed_without_admin_password(): void
    {
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        $this->inEnvironment('production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD must be set in production');

        $this->runSeeder();
    }

    public function test_production_seeds_normally_when_admin_password_is_set(): void
    {
        $_ENV['ADMIN_PASSWORD'] = 'Sup3rs3cret!';
        $this->inEnvironment('production');

        $this->runSeeder();

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@crime-alert.local'))->firstOrFail();
        $this->assertTrue((bool) $admin->isAdmin);
        $this->assertTrue(\Hash::check('Sup3rs3cret!', $admin->password));
    }

    public function test_local_still_falls_back_so_ci_seeding_keeps_working(): void
    {
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        $this->inEnvironment('local');

        $this->runSeeder(); // must not throw

        $this->assertDatabaseHas('users', ['email' => 'admin@crime-alert.local']);
    }
}
