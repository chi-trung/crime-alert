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
 *
 * Issue #183: ADMIN_* now reach the seeder through config('admin.*'), not
 * env(). That mirrors what actually happens after `php artisan
 * config:cache` (which every deploy recipe runs before migrate --seed):
 * .env stops being read at runtime, so the seeder must consume the values
 * baked into config at cache time. Feeding $_ENV mid-test — as the old
 * tests did — no longer represents the deploy contract; config overrides
 * do. Separate tests pin the config file itself so the env()->config()
 * wiring can't rot either.
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
        unset($_ENV['ADMIN_EMAIL'], $_SERVER['ADMIN_EMAIL']);
        parent::tearDown();
    }

    public function test_production_refuses_to_seed_without_admin_password(): void
    {
        $this->inEnvironment('production');
        // Freshly booted app + no ADMIN_PASSWORD in .env => null at cache time.
        $this->assertNull(config('admin.password'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD must be set in production');

        $this->runSeeder();
    }

    public function test_production_refuses_to_seed_with_an_empty_admin_password(): void
    {
        // Set-but-empty is the same hole as unset.
        $this->inEnvironment('production');
        config(['admin.password' => '']);

        $this->expectException(\RuntimeException::class);

        $this->runSeeder();
    }

    public function test_production_seeds_normally_when_admin_password_is_set(): void
    {
        $this->inEnvironment('production');
        config(['admin.password' => 'Sup3rs3cret!']);

        $this->runSeeder();

        $admin = User::where('email', config('admin.email'))->firstOrFail();
        $this->assertTrue((bool) $admin->isAdmin);
        $this->assertTrue(\Hash::check('Sup3rs3cret!', $admin->password));
    }

    public function test_local_still_falls_back_so_ci_seeding_keeps_working(): void
    {
        $this->inEnvironment('local');

        $this->runSeeder(); // must not throw

        $this->assertDatabaseHas('users', ['email' => 'admin@crime-alert.local']);
    }

    /**
     * Issue #183, the cached-config half: with config cached, runtime env()
     * sees nothing, so the seeder MUST read config('admin.*'). Pin that by
     * seeding from a config-only override — no ADMIN_EMAIL/ADMIN_PASSWORD
     * anywhere in the environment. Pre-fix (seeder calling env directly),
     * 'custom@' / 'From-Config-Only!' are invisible to it and the run
     * falls back to the public defaults instead.
     */
    public function test_seeder_reads_admin_values_from_config_not_env(): void
    {
        unset($_ENV['ADMIN_EMAIL'], $_SERVER['ADMIN_EMAIL']);
        unset($_ENV['ADMIN_PASSWORD'], $_SERVER['ADMIN_PASSWORD']);
        $this->inEnvironment('local');
        config([
            'admin.email' => 'custom-admin@example.com',
            'admin.password' => 'From-Config-Only!',
            'admin.name' => 'Config Named Admin',
        ]);

        $this->runSeeder();

        $admin = User::where('email', 'custom-admin@example.com')->firstOrFail();
        $this->assertSame('Config Named Admin', $admin->name);
        $this->assertTrue(\Hash::check('From-Config-Only!', $admin->password));
        // The public fallback must NOT have been seeded alongside it.
        $this->assertDatabaseMissing('users', ['email' => 'admin@crime-alert.local']);
    }

    /**
     * Issue #183, the wiring half: config/admin.php must bake the env
     * values in at cache time. Requiring the file evaluates its env()
     * calls against the current repository, so setting ADMIN_* first and
     * then requiring proves the defaults AND the overrides. Pre-fix the
     * file does not exist at all, so this test cannot pass.
     */
    public function test_admin_config_file_bakes_env_values(): void
    {
        $_ENV['ADMIN_EMAIL'] = $_SERVER['ADMIN_EMAIL'] = 'env-admin@example.com';
        $_ENV['ADMIN_PASSWORD'] = $_SERVER['ADMIN_PASSWORD'] = 'Env-Baked-Pw!';

        $config = require base_path('config/admin.php');

        $this->assertSame('env-admin@example.com', $config['email']);
        $this->assertSame('Env-Baked-Pw!', $config['password']);
        // ADMIN_NAME unset => documented default.
        $this->assertSame('Administrator', $config['name']);
    }
}
