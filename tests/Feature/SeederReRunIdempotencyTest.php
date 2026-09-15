<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Issue #313 (r15/seeder-console): DatabaseSeeder::run()'s demo-user arm was
 * an unconditional User::factory()->create() on a fixed email, and
 * users.email is UNIQUE. The first seed inserts the row; ANY second seed —
 * `db:seed` twice, or the repo's own "essentially every deploy recipe runs
 * migrate --seed" doctrine applied to a database that was already seeded —
 * threw QueryException 23000 ("Duplicate entry 'test@example.com'") straight
 * out of run(), which aborted before the $this->call(AdminUserSeeder::class)
 * line below it. AdminUserSeeder is the part that IS idempotent: its #273
 * branch rotates an existing admin's password, nulls its remember_token and
 * sweeps its sessions — the credential-rotation path a re-seed exists for.
 * A deploy that re-seeded to pick up a rotated ADMIN_PASSWORD therefore did
 * nothing except fail, and the loud crash pointed at the demo row, not at
 * the skipped rotation.
 *
 * The fix turns the demo arm into skip-if-present (a line note, not a warn:
 * a re-seed is a normal operation) and lets run() reach AdminUserSeeder on
 * every re-run. Skipping rather than firstOrCreate-ing is deliberate twice
 * over: rotating DEMO state was never this seeder's job (a drifted name or
 * password on test@example.com is local fixture state, not a credential to
 * renew), and the #273 lesson says an email an operator does not control
 * must not be force-rewritten by a seed — if someone registered the public
 * demo address first (#273's exact premise, demo flavor), a re-seed leaves
 * that account as the registrant's non-admin row instead of adopting it.
 *
 * Red-on-main: every test below dies on the duplicate-key QueryException
 * from seed #2 before reaching any assertion; the fix makes seed #2 a
 * success whose only visible effects are the admin-arm ones.
 */
class SeederReRunIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): void
    {
        // Same idiom as DemoUserProductionGuardTest: db:seed --force so the
        // command-level interactive confirm cannot interfere under the
        // mocked console — the defect lives in the seeder, not the command.
        Artisan::call('db:seed', ['--force' => true]);
    }

    public function test_a_second_seed_completes_instead_of_aborting_on_the_demo_email(): void
    {
        $this->runSeeder();
        $before = User::count();

        try {
            $this->runSeeder();
        } catch (QueryException $e) {
            $this->fail('re-seed must not abort: '.$e->getMessage());
        }

        // Demo row + admin row, exactly once each: the second seed neither
        // duplicated nor crashed.
        $this->assertSame($before, User::count());
        $this->assertSame(1, User::where('email', 'test@example.com')->count());
        $this->assertSame(1, User::where('email', config('admin.email'))->count());
    }

    public function test_a_second_seed_still_rotates_the_admin_credentials_it_previously_reached_only_once(): void
    {
        config(['admin.password' => 'FirstRotation!1']);
        $this->runSeeder();
        $admin = User::where('email', config('admin.email'))->firstOrFail();
        $this->assertTrue(password_verify('FirstRotation!1', $admin->password));
        // Give rotation something real to take away: an issued cookie.
        $admin->forceFill(['remember_token' => 'issued-cookie-token'])->save();

        config(['admin.password' => 'SecondRotation!9']);
        $this->runSeeder();

        $admin->refresh();
        // Pre-fix this line is unreachable (seed #2 threw on the demo row),
        // which is the whole defect: the rotation AdminUserSeeder performs
        // when the address is already its own never ran.
        $this->assertTrue(password_verify('SecondRotation!9', $admin->password), 're-seed must apply the new ADMIN_PASSWORD');
        $this->assertFalse(password_verify('FirstRotation!1', $admin->password));
        $this->assertNull($admin->remember_token, 'rotation must end the old remember-me cookie power');
    }

    public function test_a_second_seed_leaves_the_existing_demo_user_untouched(): void
    {
        $this->runSeeder();
        $demo = User::where('email', 'test@example.com')->firstOrFail();
        $demo->forceFill(['name' => 'Renamed By Local Dev'])->save();

        $this->runSeeder();

        // Skip, not update: a re-seed must not rewrite local fixture state
        // (and must not adopt a foreign row squatting the public demo
        // email, which firstOrCreate-style semantics would).
        $this->assertSame('Renamed By Local Dev', $demo->fresh()->name);
    }
}
