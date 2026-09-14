<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #266: ProfileController::destroy() ran four Eloquent sweeps and the
 * user delete as five independently autocommitted statements, and each
 * sweep's snapshot SELECT stayed open long after it. Two shapes, one fix —
 * a single transaction whose first statement takes the users row with
 * lockForUpdate(), each class swept to a fixed point, logout only after
 * commit.
 *
 * Shape 1 (atomicity): a throw in a late sweep used to leave the earlier
 * loops' committed destruction behind — alerts, experiences, comments and
 * their images permanently gone while the account survived, still logged
 * in. Inside the transaction the rollback restores every row. (Disk
 * unlinks already executed inside model hooks cannot roll back — the
 * honest residual, documented in the controller: rows come back with
 * broken image links, which is the recoverable half, unlike the old
 * permanently destroyed content.)
 *
 * Shape 2 (window): a second tab's POST /alerts committing after the
 * sweep's SELECT but before $user->delete() created a row that the FK
 * cascade then destroyed WITHOUT firing Alert::deleting — orphaning its
 * just-stored image, skipping the #57/#115 likes/notification sweeps, and
 * leaving admin bells on a dead post_id. On MySQL the parent-row lock
 * blocks such an insert until after the delete, where it dies as an honest
 * FK error; on sqlite (where this suite runs, and where the issue's race
 * idiom simulates the mid-flight commit through a hook) the lock is a
 * no-op, so the fixed-point sweep must catch the row and destroy it
 * through its own hook instead.
 */
class AccountDeletionRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function ownerWithContent(): User
    {
        $owner = User::factory()->create();
        Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd',
            'status' => 'approved', 'image' => 'alerts/a.png',
        ]);
        Storage::disk('public')->put('alerts/a.png', 'A');

        return $owner;
    }

    public function test_a_mid_sweep_alert_created_by_the_same_session_dies_through_its_own_hook(): void
    {
        // Shape 2, sqlite-visible form: while the first Alert::deleting
        // runs, a second tab's already-validated store() commits a fresh
        // alert for this same (still-authenticated) user, image on disk and
        // all. Pre-fix that row was invisible to the snapshot SELECT, so
        // $user->delete()'s FK cascade destroyed it event-lessly: the image
        // was orphaned forever and the likes/notification sweeps never ran.
        // Post-fix the sweep runs to a fixed point, so the late row meets
        // Alert::deleting on its own terms: its file freed with it.
        $owner = $this->ownerWithContent();

        $inserted = false;
        Alert::deleting(function (Alert $alert) use ($owner, &$inserted): void {
            if ($inserted) {
                return;
            }
            $inserted = true;
            // The rival tab's committed write, deliberately RAW so it fires
            // no events of its own on insert — exactly what a mid-flight
            // INSERT/commit looks like to the sweep loop.
            Storage::disk('public')->put('alerts/mid.png', 'MID');
            DB::table('alerts')->insert([
                'user_id' => $owner->id,
                'title' => 'Mid-flight',
                'description' => 'd',
                'status' => 'approved',
                'image' => 'alerts/mid.png',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($owner)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        // The mid-flight row is gone...
        $this->assertSame(0, DB::table('alerts')->count());
        // ...but unlike the event-less cascade, its hook ran: no orphan
        // files survive on the public disk (both alerts' images freed).
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertNull($owner->fresh());
    }

    public function test_a_throw_in_a_late_sweep_rolls_the_whole_teardown_back(): void
    {
        // Shape 1: pre-fix, this throw (a per-subtree notification sweep
        // blowing max_allowed_packet is the issue's real-world example) hit
        // after alerts/experiences/comments had already committed their
        // destruction — account alive, content permanently gone. Inside one
        // transaction, every committed-looking delete is undone.
        $owner = $this->ownerWithContent();
        Experience::forceCreate([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 'E',
            'content' => 'c', 'status' => 'approved',
        ]);
        $alert = Alert::where('user_id', $owner->id)->first();
        Comment::forceCreate([
            'alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'c',
        ]);
        $thread = SupportRequest::forceCreate([
            'user_id' => $owner->id, 'subject' => 'S', 'status' => 'open',
        ]);

        SupportRequest::deleting(function (SupportRequest $request) use ($thread): void {
            if ($request->id === $thread->id) {
                throw new RuntimeException('simulated mid-sequence failure');
            }
        });

        // withoutExceptionHandling: the simulated hook failure must reach
        // the test as the exception it is. Under default test exception
        // handling Laravel renders the throw as a 500 response instead,
        // which is how the pre-fix run here "passed" the throw past the
        // catch block — the red this pin must produce is about the
        // COMMITTED destruction left behind, not about response codes.
        try {
            $this->withoutExceptionHandling();
            $this->actingAs($owner)->delete('/profile', ['password' => 'password']);
            $this->fail('the simulated sweep failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated mid-sequence failure', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        // Nothing half-happened: account and every owned row survive.
        $this->assertNotNull($owner->fresh());
        $this->assertSame(1, DB::table('alerts')->count());
        $this->assertSame(1, DB::table('experiences')->count());
        $this->assertSame(1, DB::table('comments')->count());
        $this->assertSame(1, DB::table('support_requests')->count());
        // The session survives too — logout now happens only after a
        // committed teardown, so a rolled back one must not log anyone out.
        $this->assertAuthenticatedAs($owner);
    }

    public function test_honest_deletion_still_removes_everything_and_logs_out(): void
    {
        // The #53/#57/#115/#61/#191 guarantees through the new transaction:
        // rows gone, images freed, no notifications left pointing at dead
        // posts, guest after the redirect.
        $owner = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd',
            'status' => 'approved', 'image' => 'alerts/a.png',
        ]);
        Storage::disk('public')->put('alerts/a.png', 'A');
        $other = User::factory()->create();
        $other->notify(new NewPostNotification($alert, $other, 'alert'));

        $this->actingAs($owner)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSame(0, DB::table('users')->where('id', $owner->id)->count());
        $this->assertSame(0, DB::table('alerts')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(
            0,
            DB::table('notifications')->where('data', 'like', '%"post_id":'.$alert->id.',%')->count()
        );
    }
}
