<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #309 (r14/profile-unlink): ProfileController::destroy() wraps the
 * whole account teardown in ONE DB::transaction (#266) — yet the Alert and
 * Experience `deleting` hooks unlink uploaded files INSIDE it (Alert.php's
 * #289 current read, Experience.php's avatar unlink). When a throw hits a
 * late sweep, the transaction rolls every row back but the unlinks are disk
 * operations: they cannot roll back. The rolled-back account therefore
 * RESURRECTS with alerts whose image column points at files that no longer
 * exist — broken image links for an account the user never deleted. #266
 * itself listed this as the "honest residual"; #309 closes it.
 *
 * Fix: capture-and-defer via App\Support\DeferredFileUnlinks — destroy()
 * ARMS the ledger before its transaction, the hooks add paths to it instead
 * of unlinking WHILE ARMED (still the #289 current-read value — arming
 * changes WHEN, never WHICH), destroy() DRAINS (executes) the unlinks only
 * after the transaction commits, and DISCARDS them when it throws, because
 * rolled-back rows keep their files.
 *
 * Why a hand-rolled ledger and not DB::afterCommit (settled from vendor
 * source): the manager's addCallback attaches to the ROOT transaction record
 * when the app's DB::transaction is nested under the RefreshDatabase test
 * wrapper, and the wrapper's level-1 "commit" at teardown never runs
 * executeCallbacks() (afterCommitCallbacksShouldBeExecuted(1) is false — it
 * only runs rollback callbacks). A DB::afterCommit deferral would therefore
 * SILENTLY never unlink in this CI suite, turning every #266/#289 green
 * allFiles()===[] pin into a false pass. The explicit arm/drain lives in the
 * one caller that needs it, so every other delete path keeps #289's
 * immediate-unlink semantics byte-for-byte.
 */
class ProfileDestroyDeferredUnlinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function ownerWithMedia(): User
    {
        $owner = User::factory()->create();
        Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd',
            'status' => 'approved', 'image' => 'alerts/a.png',
        ]);
        Experience::forceCreate([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 'E',
            'content' => 'c', 'status' => 'approved', 'avatar' => 'experiences/e.png',
        ]);
        Storage::disk('public')->put('alerts/a.png', 'A');
        Storage::disk('public')->put('experiences/e.png', 'E');

        return $owner;
    }

    public function test_a_rolled_back_teardown_keeps_the_files_of_the_resurrected_rows(): void
    {
        // Deterministic same-connection interleave (#266's own idiom): the
        // alert and experience rows are deleted first — their hooks fire —
        // and the throw lands in the LAST sweep, so the transaction unwinds
        // everything while the unlinks have already executed.
        $owner = $this->ownerWithMedia();
        SupportRequest::forceCreate([
            'user_id' => $owner->id, 'subject' => 'S', 'status' => 'open',
        ]);

        SupportRequest::deleting(function (SupportRequest $request) use ($owner): void {
            if ($request->user_id === $owner->id) {
                throw new RuntimeException('simulated late-sweep failure');
            }
        });

        try {
            $this->withoutExceptionHandling();
            $this->actingAs($owner)->delete('/profile', ['password' => 'password']);
            $this->fail('the simulated sweep failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated late-sweep failure', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        // Rows survived — that half already passed since #266.
        $this->assertNotNull($owner->fresh());
        $this->assertSame(1, DB::table('alerts')->count());
        $this->assertSame(1, DB::table('experiences')->count());

        // Pre-fix RED: the deletes' hooks unlinked alerts/a.png and
        // experiences/e.png INSIDE the rolled-back transaction — the alert
        // row is alive with image='alerts/a.png' but the file is gone, every
        // viewer gets a broken image on a post nobody deleted.
        Storage::disk('public')->assertExists('alerts/a.png');
        Storage::disk('public')->assertExists('experiences/e.png');
    }

    public function test_a_committed_teardown_still_frees_every_captured_file(): void
    {
        // The ledger must not become a silent leak on the happy path: after
        // the commit the drained unlinks run and nothing survives on disk
        // (the #266/#289 endpoint this pin protects).
        $owner = $this->ownerWithMedia();

        $this->actingAs($owner)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertDatabaseCount('alerts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
