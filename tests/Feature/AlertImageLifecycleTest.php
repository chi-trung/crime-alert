<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #289: four leaks in the alert image lifecycle that #233/#255/#267
 * each left one layer down.
 *
 * 1. destroy() (and ProfileController's account sweep) unlink the image from
 *    the Alert's IN-MEMORY value — the route binding's snapshot. A rival
 *    replacement that commits between binding hydration and the DELETE moves
 *    the column to a fresh path; deleting() then unlinks the stale one (a
 *    no-op, the rival already freed it) and the live file keeps zero
 *    referencing rows forever.
 * 2. update() stores the replacement on the public disk BEFORE the
 *    transaction, and that transaction is not wrapped — #267 learned this
 *    lesson in store() only. Any throw inside it (the 1205 the row lock at
 *    the first statement invites, a connection drop) rolls the row back,
 *    500s, and permanently orphans the just-written file.
 * 3. The winner's only unlink of the OLD file sits AFTER the post-commit
 *    admin fan-out. If ringing the bells throws (the notification broker, a
 *    dropped connection), the response is a 500 over a committed swap whose
 *    displaced file nothing will ever free — deleting() later unlinks only
 *    the new path.
 *
 * Every race is armed with the repo-standard #163 retrieved() idiom on the
 * single test connection, so it reproduces identically on sqlite and MySQL.
 */
class AlertImageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @return array{0: User, 1: Alert}
     */
    private function ownerWithApprovedAlert(): array
    {
        $owner = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id,
            'title' => 'Cảnh báo cũ',
            'description' => 'd',
            'status' => 'approved',
            'image' => 'alerts/old.png',
        ]);
        Storage::disk('public')->put('alerts/old.png', 'OLD');

        return [$owner, $alert];
    }

    /**
     * Arm the committed rival replacement: the first time an Alert hydrates
     * (the route binding, or the first row of the profile sweep), the rival's
     * write lands — column moved to alerts/rival.png, its own old file
     * already unlinked. The hydrated model keeps the stale in-memory value,
     * exactly as a real loser of that race would.
     */
    private function armRivalReplacementOnce(): void
    {
        $armed = true;
        Alert::retrieved(function (Alert $model) use (&$armed): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            Storage::disk('public')->put('alerts/rival.png', 'RIVAL');
            DB::table('alerts')->where('id', $model->id)->update(['image' => 'alerts/rival.png']);
            Storage::disk('public')->delete('alerts/old.png');
        });
    }

    public function test_destroy_landing_after_a_rival_swap_frees_the_live_file_not_the_stale_one(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $this->armRivalReplacementOnce();

        $this->actingAs($owner)
            ->delete("/alerts/{$alert->id}")
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);

        // Pre-fix: deleting() unlinked the binding's alerts/old.png (already
        // gone), and alerts/rival.png — the path the row actually carried at
        // delete time — sat on the public disk with no row referencing it.
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_account_teardown_after_a_rival_swap_frees_the_live_file(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();
        $this->armRivalReplacementOnce();

        $this->actingAs($owner)->delete('/profile', ['password' => 'password'])->assertRedirect();

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);

        // #266's users-row lock does not cover the alert rows the sweep
        // hydrates, so the same stale-binding unlink reproduces here.
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_transaction_throwing_after_the_replacement_upload_frees_the_fresh_file(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();

        // The binding hydration is alert #1; #2 is the in-transaction
        // $alert->refresh(). Failing there mimics the honest cases #267
        // listed for store(): a 1205 against the row lock the transaction
        // takes, or a connection drop mid-write.
        $hydrations = 0;
        Alert::retrieved(function (Alert $model) use (&$hydrations): void {
            if (! $model->exists) {
                return;
            }
            $hydrations++;
            if ($hydrations === 2) {
                throw new RuntimeException('connection dropped mid-transaction');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->put("/alerts/{$alert->id}", [
                'title' => 'Sửa gãy giữa transaction',
                'description' => 'd',
                'image' => UploadedFile::fake()->image('fresh.png'),
            ]);
            $this->fail('Expected the mid-transaction throw to surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('connection dropped mid-transaction', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        $this->assertSame(2, $hydrations);

        // The row rolled back whole — and the file this request had ALREADY
        // written to disk must have rolled back with it. Pre-fix it survived
        // the 500 with no row ever pointing at it.
        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'status' => 'approved',
            'image' => 'alerts/old.png',
        ]);
        $this->assertSame(['alerts/old.png'], Storage::disk('public')->allFiles());
    }

    public function test_a_failing_bell_fanout_after_commit_still_frees_the_displaced_file(): void
    {
        [$owner, $alert] = $this->ownerWithApprovedAlert();

        // The transaction commits first; the admin fan-out after it writes
        // notifications synchronously. Fail on the first admin hydration so
        // the throw lands strictly between COMMIT and the unlink.
        $armed = true;
        User::retrieved(function (User $user) use (&$armed): void {
            if (! $armed || ! $user->isAdmin) {
                return;
            }
            $armed = false;
            throw new RuntimeException('notification broker down');
        });
        $admin = User::factory()->create(['isAdmin' => true]);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($owner)->put("/alerts/{$alert->id}", [
                'title' => 'Về hàng đợi, bell lỗi',
                'description' => 'd',
                'image' => UploadedFile::fake()->image('fresh.png'),
            ]);
            $this->fail('Expected the fan-out throw to surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('notification broker down', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        $this->assertNotNull($admin->id);

        // The swap IS durable (committed before the fan-out), so its old file
        // has no row left to free it later — the unlink had to happen BEFORE
        // the bells. Pre-fix, old.png outlived the 500 permanently.
        $live = DB::table('alerts')->where('id', $alert->id)->first();
        $this->assertSame('pending', $live->status);
        $this->assertNotSame('alerts/old.png', $live->image);
        Storage::disk('public')->assertExists($live->image);
        Storage::disk('public')->assertMissing('alerts/old.png');
    }
}
