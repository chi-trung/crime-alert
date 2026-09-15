<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #285: close()'s final re-read — the one that decides 404-vs-'already
 * closed' when the conditional UPDATE matched nothing — was a plain exists(),
 * the last un-locked read in this controller's #163 family (its siblings all
 * lock: sendMessage L114, store L267/L297, destroy L464). Under MySQL's
 * REPEATABLE READ a non-locking read returns the snapshot taken at the first
 * statement of the transaction, so a rival destroy() committed inside the
 * value('status') -> exists() window was invisible to it: the UPDATE correctly
 * touched 0 rows (writes are always current reads) yet exists() still reported
 * the vanished thread as alive, and the admin got
 * 'Yêu cầu này đã được đóng trước đó.' instead of the honest #247 404.
 * lockForUpdate() turns the re-read into a current read, closing the same
 * window #163 pins everywhere else.
 *
 * MySQL-only, and for two honest reasons: (1) the interleave needs two live
 * connections to the SAME committed database — under sqlite the phpunit.xml
 * DB_DATABASE=:memory: gives every connection its own private database
 * (SQLiteConnector), so no rival write is ever visible across connections;
 * and (2) on sqlite lockForUpdate() is a documented no-op, so the exact clause
 * under test emits no SQL there — pinning it would be vacuous. The rival's
 * thread (and the owner it FKs to) is committed on a second connection, the
 * request connection's transaction is re-opened so its fresh snapshot can
 * include those rows, then the first read pins 'open' and the rival DELETE
 * commits right behind it — the deterministic mid-transaction rival commit.
 */
class SupportCloseStaleSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function mysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Two-connection MVCC interleave needs MySQL (see class doc).');
        }
    }

    /**
     * A second, independent connection to the same MySQL database — its own
     * REPEATABLE READ view and, outside any transaction, autocommit.
     */
    private function secondConnection(): Connection
    {
        $base = DB::connection()->getName();
        config(["database.connections.{$base}_rival" => config("database.connections.{$base}")]);

        return DB::connection("{$base}_rival");
    }

    public function test_rival_delete_committed_after_the_status_read_still_404s_under_mysql(): void
    {
        $this->mysql();
        $second = $this->secondConnection();

        // The rival's thread is inserted and COMMITTED on the second
        // connection, so it exists in durably-committed form for both MVCC
        // views. (It cannot be an uncommitted write of the main connection:
        // InnoDB holds an exclusive lock on a row an open transaction just
        // inserted, so a rival DELETE of it would block on that lock, not
        // commit.)
        $ownerId = $second->table('users')->insertGetId([
            'name' => 'Owner', 'email' => 'rival-owner@example.test', 'isAdmin' => 0,
            'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $threadId = $second->table('support_requests')->insertGetId([
            'user_id' => $ownerId, 'subject' => 'vanishing', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // These rows are COMMITTED on the rival connection, i.e. real table
        // state RefreshDatabase's rollback will not undo — clean them up
        // unconditionally so the MySQL leg's other 515 tests never see them.
        $this->beforeApplicationDestroyed(function () use ($second, $ownerId, $threadId): void {
            $second->table('support_requests')->where('id', $threadId)->delete();
            $second->table('users')->where('id', $ownerId)->delete();
        });
        $main = DB::connection();

        // RefreshDatabase's setUp opened its wrap-transaction BEFORE these rows
        // existed, and its migration queries already pinned this connection's
        // REPEATABLE READ snapshot, so the main connection cannot see the rival
        // rows yet (probe: attempt-1 404s at the very first read). Re-open the
        // transaction to take a fresh snapshot — the transaction close() then
        // runs inside (DB::transaction on an open connection is a savepoint,
        // so it does NOT start a new view). Nothing is lost by the rollback:
        // the schema was committed before setUp, and no test row has been
        // written on this connection yet.
        $main->rollBack();
        $main->beginTransaction();

        // This first consistent read PINS the view while the row is 'open'...
        $this->assertSame('open', $main->table('support_requests')->where('id', $threadId)->value('status'));

        // ...and the rival DELETE commits immediately after it. From here on,
        // every non-locking read in this transaction — close()'s L403 guard
        // and the L431 re-read alike — sees 'open', while every current read
        // (the conditional UPDATE, and the fixed FOR UPDATE exists) sees the
        // row gone. That is exactly the mid-transaction rival commit #285
        // describes, reached deterministically instead of via a query hook.
        $second->table('support_requests')->where('id', $threadId)->delete();

        // The admin is an uncommitted own-write of this same connection, so
        // auth()/isAdmin read it regardless of the pinned snapshot.
        $admin = User::factory()->admin()->create();

        // Without the fix: UPDATE matches 0 rows (current read), but the plain
        // exists() answers from the stale snapshot -> 'already closed' flash,
        // and assertNotFound goes RED. With the fix: FOR UPDATE is a current
        // read, the delete is visible, close() returns null -> honest 404.
        $this->actingAs($admin)
            ->post(route('admin.support.close', $threadId))
            ->assertNotFound();
    }

    public function test_plain_exists_reads_the_stale_snapshot_and_for_update_does_not(): void
    {
        // Pins the raw mechanism the one-line fix rests on, independent of the
        // controller: after a rival commits a DELETE, a non-locking re-read in
        // the same open transaction still reports the row (the bug), while the
        // FOR UPDATE re-read correctly reports it gone (the fix). Red-pinning
        // this guards against a future refactor quietly dropping lockForUpdate.
        $this->mysql();
        $second = $this->secondConnection();
        $ownerId = $second->table('users')->insertGetId([
            'name' => 'Owner2', 'email' => 'rival-owner2@example.test', 'isAdmin' => 0,
            'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $threadId = $second->table('support_requests')->insertGetId([
            'user_id' => $ownerId, 'subject' => 'snap', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Same committed-on-the-rival cleanup duty as test 1: the DELETE below
        // removes the thread, but the owner row outlives RefreshDatabase's
        // rollback and must not leak into the other 515 MySQL tests.
        $this->beforeApplicationDestroyed(function () use ($second, $ownerId, $threadId): void {
            $second->table('support_requests')->where('id', $threadId)->delete();
            $second->table('users')->where('id', $ownerId)->delete();
        });

        $main = DB::connection();
        $main->beginTransaction();
        // Open the snapshot with the same read close() starts with.
        $wasOpen = $main->table('support_requests')->where('id', $threadId)->value('status');
        $second->table('support_requests')->where('id', $threadId)->delete(); // rival commits (autocommit)
        $snapshot = (bool) $main->selectOne('select exists(select * from `support_requests` where `id` = ?) e', [$threadId])->e;
        $locking = (bool) $main->selectOne('select exists(select * from `support_requests` where `id` = ? for update) e', [$threadId])->e;
        $main->rollBack();

        $this->assertSame('open', $wasOpen, 'precondition: the row was open when the snapshot opened');
        $this->assertTrue($snapshot, 'premise: the non-locking re-read sees the stale, still-present row (the #285 bug)');
        $this->assertFalse($locking, 'premise: FOR UPDATE forces a current read and sees the committed rival delete (the fix)');
    }
}
