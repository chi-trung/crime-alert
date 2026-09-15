<?php

namespace Tests\Feature;

use App\Http\Controllers\SupportRequestController;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #307 (r14/support-destroy): destroy()'s #271 lock-first statement is
 * `SupportRequest::whereKey(...)->lockForUpdate()->pluck('id')` — and the
 * pluck result is DISCARDED. When a rival destroy() commits its DELETE
 * between the route binding (which hydrates $supportRequest from the stale
 * snapshot on MySQL) and this transaction, the FOR UPDATE read — a current
 * read, per #163/#285 — correctly finds nothing, but the code proceeds
 * anyway: $supportRequest->delete() touches 0 rows, the sweep runs over
 * already-swept rows, and L497 flashes 'Đã xóa yêu cầu hỗ trợ!' for a thread
 * this request did not delete. close() faced the identical window and #256/
 * #285 answered it with an honest 404 (#247); destroy() was never given the
 * same branch. Fix mirrors close()'s shape: the lock probe's ANSWER becomes
 * the branch — vanished thread 404s, no success flash for someone else's
 * delete. Same two-connection MySQL interleave as SupportCloseStaleSnapshotTest.
 */
class SupportDestroyVanishedThreadTest extends TestCase
{
    use RefreshDatabase;

    private function mysql(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Two-connection MVCC interleave needs MySQL (see class doc).');
        }
    }

    private function secondConnection(): Connection
    {
        $base = DB::connection()->getName();
        config(["database.connections.{$base}_rival" => config("database.connections.{$base}")]);

        return DB::connection("{$base}_rival");
    }

    public function test_rival_delete_committed_inside_the_window_404s_instead_of_flashing_success(): void
    {
        $this->mysql();
        $second = $this->secondConnection();

        // Thread and owner COMMITTED on the rival connection so both MVCC
        // views can see them (an uncommitted own-insert would make the rival
        // DELETE block on the row's X lock, not slip through — same reasoning
        // as SupportCloseStaleSnapshotTest).
        $ownerId = $second->table('users')->insertGetId([
            'name' => 'Owner', 'email' => 'vanish-owner@example.test', 'isAdmin' => 0,
            'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $threadId = $second->table('support_requests')->insertGetId([
            'user_id' => $ownerId, 'subject' => 'vanishing', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->beforeApplicationDestroyed(function () use ($second, $ownerId, $threadId): void {
            $second->table('support_requests')->where('id', $threadId)->delete();
            $second->table('users')->where('id', $ownerId)->delete();
        });
        $main = DB::connection();

        // RefreshDatabase's setUp pinned this connection's REPEATABLE READ
        // view before the rival rows existed — re-open it so a fresh snapshot
        // includes them, exactly as the close() precedent does.
        $main->rollBack();
        $main->beginTransaction();

        // First consistent read pins the view while the row is alive; the
        // rival DELETE commits right behind it. From here the route binding
        // (a non-locking select) still resolves the row — destroy() RUNS —
        // while its lockForUpdate probe, a current read, sees it gone.
        $this->assertSame('open', $main->table('support_requests')->where('id', $threadId)->value('status'));
        $second->table('support_requests')->where('id', $threadId)->delete();

        $admin = User::factory()->admin()->create();

        // Without the fix: pluck answer discarded, delete() touches 0 rows,
        // L497 flashes success for a thread this request never deleted —
        // assertNotFound goes RED. With the fix: the lock probe's answer
        // branches, honest #247 404, no flash.
        $this->actingAs($admin)
            ->from('/admin/support')
            ->delete(route('admin.support.destroy', $threadId))
            ->assertNotFound();

        $this->assertArrayNotHasKey(
            'success',
            session()->all(),
            'the vanished-thread response still flashes a success message'
        );
    }

    public function test_destroy_consumes_the_lock_probe_and_404s_on_vanished(): void
    {
        // Wiring pin (engine-agnostic, mirrors #256's doctrine): the FOR
        // UPDATE probe added by #271 must actually DECIDE something — its
        // result is consumed, and the vanished branch aborts 404 like
        // close()'s. A refactor that drops back to a fire-and-forget pluck
        // re-opens the silent false-success.
        $rm = new \ReflectionMethod(SupportRequestController::class, 'destroy');
        $src = implode('', \array_slice(
            \file($rm->getFileName()),
            $rm->getStartLine() - 1,
            $rm->getEndLine() - $rm->getStartLine() + 1
        ));

        $this->assertMatchesRegularExpression(
            '/lockForUpdate\(\)->exists\(\)/',
            $src,
            'the #271 lock probe result is discarded again — pluck() instead of exists() means a vanished thread will flash success'
        );
        $this->assertMatchesRegularExpression(
            '/abort\(404\)/',
            $src,
            'destroy() has no honest 404 branch for a thread deleted by a rival (close() got one in #256)'
        );
    }

    public function test_honest_delete_still_flashes_success_and_removes_the_thread(): void
    {
        // UX control: the gate must not eat real deletions — a live thread
        // still 302s back with the success flash and the row goes away.
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Sống', 'status' => 'open']);

        $this->actingAs($admin)
            ->from('/admin/support')
            ->delete(route('admin.support.destroy', $thread))
            ->assertRedirect('/admin/support')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('support_requests', ['id' => $thread->id]);
    }
}
