<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #273: AdminUserSeeder keyed its idempotence on EMAIL alone —
 * updateOrCreate(['email' => config('admin.email')]) — and the shipped
 * default of that config is a public, valid, ownerless address
 * ('admin@crime-alert.local') that passes every POST /register rule. An
 * attacker registering it first turned the next deploy's `migrate --seed`
 * into a promotion: the seeder matched the attacker's row, rewrote its
 * credentials, and forceFill(isAdmin=true) handed over the admin panel —
 * with the live session and remember token untouched, so escalation was
 * instant. The fix treats isAdmin (not fillable, registration defaults
 * it false) as the provenance marker: seed inserts a new admin, re-seeds
 * only an account that is already admin (with #27/#253 rotation hygiene
 * on the reset), and hard-throws when the configured address belongs to
 * someone who is not admin.
 */
class AdminSeederTakeoverGuardTest extends TestCase
{
    use RefreshDatabase;

    private function email(): string
    {
        return config('admin.email');
    }

    public function test_a_registered_takeover_of_the_admin_email_does_not_become_admin(): void
    {
        $attacker = User::factory()->create([
            'email' => $this->email(),
            'isAdmin' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to seed');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_the_takeover_attempt_leaves_the_account_and_credentials_untouched(): void
    {
        $attacker = User::factory()->create([
            'email' => $this->email(),
            'isAdmin' => false,
        ]);

        try {
            $this->seed(AdminUserSeeder::class);
            $this->fail('the seeder must refuse to take over a non-admin account');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Refusing to seed', $e->getMessage());
        }

        $fresh = $attacker->fresh();
        // Not promoted, not password-reset, not re-verified under the
        // seeder's name — every field the old updateOrCreate rewrote.
        $this->assertFalse((bool) $fresh->isAdmin);
        $this->assertSame($attacker->password, $fresh->password);
        $this->assertSame($attacker->name, $fresh->name);
        $this->assertSame($attacker->email_verified_at?->toDateTimeString(), $fresh->email_verified_at?->toDateTimeString());
    }

    public function test_a_fresh_install_still_seeds_a_working_verified_admin(): void
    {
        $this->assertSame(0, User::where('email', $this->email())->count());

        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', $this->email())->first();
        $this->assertNotNull($admin, 'fresh installs must still get their admin');
        $this->assertTrue((bool) $admin->isAdmin);
        // email_verified_at rode forceFill with isAdmin — a "not fillable"
        // slip would ship an admin locked out of every verified-gated route.
        $this->assertNotNull($admin->email_verified_at);
        // The seeded credentials must actually authenticate.
        $this->assertTrue(
            Hash::check(config('admin.password') ?? 'ChangeMe!123', $admin->password)
        );
    }

    public function test_reseeding_the_managed_admin_resets_credentials_and_rotates_sessions(): void
    {
        $this->seed(AdminUserSeeder::class);
        $admin = User::where('email', $this->email())->firstOrFail();

        // Stale long-lived credentials as a pre-reset snapshot: a stolen
        // remember token and a database session row.
        $admin->forceFill(['remember_token' => 'stale-remember-token'])->save();
        if (! DB::getSchemaBuilder()->hasTable('sessions')) {
            // SESSION_DRIVER=array in tests: create the table the
            // database driver uses so the sweep arm has something to sweep.
            Schema::create('sessions', function ($table): void {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->text('payload');
                $table->integer('last_activity')->index();
            });
        }
        DB::table('sessions')->insert([
            'id' => 'stale-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->seed(AdminUserSeeder::class);

        $fresh = $admin->fresh();
        $this->assertNull($fresh->remember_token, 'rotation doctrine #27/#123/#253: old cookies must die');
        $this->assertSame(
            0,
            DB::table('sessions')->where('user_id', $admin->id)->count(),
            'database sessions of the reset admin must be swept'
        );
    }
}
