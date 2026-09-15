<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #277: DatabaseSeeder::run() created its demo account
 * (User::factory()->create(['email' => 'test@example.com'])) with no
 * environment guard at all. The factory ships email_verified_at=now() and
 * password=Hash::make('password') — applied via Model::unguarded, so the
 * non-fillable verified timestamp lands too — which means `migrate --seed`
 * (the repo's own deploy doctrine: config/admin.php "essentially every
 * recipe") planted a fully-verified account whose credentials are printed
 * in this public repo: anonymous login → write access (alerts, comments,
 * support) under "Test User" on a live board. AdminUserSeeder carries #45's
 * production guard for exactly this "public knowledge" asymmetry; the demo
 * user is the same kind of credential with no guard and was even created
 * BEFORE AdminUserSeeder ran, so it survived a production seed #45 aborted.
 * The fix skips the demo user (warn, not throw — it is demo data, not a
 * misconfiguration to fail a deploy over) whenever app()->isProduction(),
 * leaving CI (APP_ENV=local/testing) seeding it untouched.
 */
class DemoUserProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    private function inEnvironment(string $env): void
    {
        // Same idiom as AdminUserSeederTest #45: Application::environment()
        // serves $app['env']; overriding the container value is the
        // supported way to flip it mid-test.
        $this->app['env'] = $env;
    }

    private function runSeeder(): void
    {
        // db:seed --force, not TestCase::seed(): seed() can't pass options
        // and the command's interactive production confirm would fatal
        // under the mocked console. Deploy scripts run with --force —
        // exactly why the guard must live in the seeder, not the command.
        Artisan::call('db:seed', ['--force' => true]);
    }

    public function test_production_seed_does_not_create_the_public_credential_demo_user(): void
    {
        $this->inEnvironment('production');
        // ADMIN_PASSWORD is #45's mandatory-in-production setting; this pin
        // is about the DEMO arm, so give the admin arm what it needs to
        // finish — pre-fix the seed threw here, hiding exactly how much of
        // the demo row survives an aborted seed in production.
        config(['admin.password' => 'Sup3r-Secret!']);

        $this->runSeeder();

        $this->assertSame(
            0,
            User::where('email', 'test@example.com')->count(),
            'a verified account with repo-printed credentials must never exist on production'
        );
    }

    public function test_production_seed_still_provisions_the_admin(): void
    {
        // The skip must not swallow the rest of the seed: AdminUserSeeder
        // runs after the demo-user arm, and a production deploy (with the
        // mandatory ADMIN_PASSWORD #45 enforces) still gets its admin.
        $this->inEnvironment('production');
        config(['admin.password' => 'Sup3r-Secret!']);

        $this->runSeeder();

        $admin = User::where('email', config('admin.email'))->first();
        $this->assertNotNull($admin, 'production seed must still create the admin');
        $this->assertTrue((bool) $admin->isAdmin);
    }

    public function test_non_production_seed_still_creates_a_verified_loginable_demo_user(): void
    {
        // The CI/dev contract stays intact: the demo user exists AND is
        // loginable with the public password. The verified pin is the one
        // that holds the Model::unguarded mechanism: email_verified_at is
        // not fillable, so a factory refactor that loses unguarded drops it
        // and this pin reddens. Env stays the default 'testing' on purpose:
        // it is the non-production arm the guard must let through, and
        // flipping $app['env'] to 'local' would also disable the CSRF test
        // exemption (VerifyCsrfToken keys off runningUnitTests()).
        $this->runSeeder();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'local/CI seed must keep the demo user');
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(password_verify('password', $user->password));

        // A real credential pair must actually authenticate over HTTP.
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_production_guard_still_refuses_to_seed_the_default_admin_password(): void
    {
        // The two guards do not interfere: #45's ADMIN_PASSWORD throw fires
        // even though the demo-user arm is the production one that skips.
        $this->inEnvironment('production');
        config(['admin.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_PASSWORD must be set in production');

        $this->runSeeder();
    }
}
