<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #102: support notifications reference their thread only inside the
 * JSON data payload (support_request_id) — the morph notifiable_* pair keys
 * the recipient, so no FK cascades them (same structural reason as #57/#61).
 * Deleting a thread must sweep exactly its own notification rows.
 */
class SupportNotificationOrphanTest extends TestCase
{
    use RefreshDatabase;

    private function orphanRows(): int
    {
        return DB::table('notifications')
            ->whereIn('type', [NewSupportRequest::class, NewSupportMessage::class])
            ->count();
    }

    public function test_deleting_a_thread_sweeps_its_notifications_only(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);
        // NewSupportRequest fans out to admins on create; a reply notifies
        // the other participant — both shapes must be swept.
        $admin->notify(new NewSupportRequest($thread, $owner));
        $msg = SupportMessage::create([
            'support_request_id' => $thread->id, 'user_id' => $admin->id, 'message' => 'm',
        ]);
        $owner->notify(new NewSupportMessage($thread, $msg, $admin));
        $this->assertSame(2, $this->orphanRows());

        // An unrelated thread's notification must survive the sweep.
        $other = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'other']);
        $admin->notify(new NewSupportRequest($other, $owner));
        $this->assertSame(3, $this->orphanRows());

        $this->actingAs($admin)
            ->delete(route('admin.support.destroy', $thread))
            ->assertRedirect();

        $this->assertSame(1, $this->orphanRows());
        $survivor = DB::table('notifications')->sole();
        $this->assertSame(NewSupportRequest::class, $survivor->type);
        $this->assertStringContainsString('"support_request_id":'.$other->id, (string) $survivor->data);
    }

    public function test_sweep_matches_thread_id_exactly_not_by_prefix(): void
    {
        // Thread 11's payload contains `"support_request_id":11` — a naive
        // `...:1%` LIKE would prefix-match it. The sweep pattern terminates
        // the id with a non-digit class, so deleting thread 1 must not eat
        // thread 11's rows. Issue #283: the 1-vs-11 shape is now pinned with
        // explicit ids instead of nine padding rows + allocator luck — the
        // sqlite AUTOINCREMENT counter rewinds with each rolled-back test,
        // MySQL's InnoDB counter never does (probe: first thread id 87).
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $first = SupportRequest::forceCreate(['id' => 1, 'user_id' => $owner->id, 'subject' => 'a']);
        $eleventh = SupportRequest::forceCreate(['id' => 11, 'user_id' => $owner->id, 'subject' => 'b']);
        $this->assertSame(1, $first->id);
        $this->assertSame(11, $eleventh->id);

        $admin->notify(new NewSupportRequest($eleventh, $owner));
        $this->assertSame(1, $this->orphanRows());

        $first->delete();

        $this->assertSame(1, $this->orphanRows());
    }

    public function test_account_deletion_sweeps_owned_threads_notifications(): void
    {
        // Issue #115: deleting the owner DB-cascaded their threads away
        // without firing SupportRequest::deleting, so the #102 sweep never
        // ran and the admin's copy survived as a 404 link (probe:
        // threads=0, msgs=0, notifs=1 after $user->delete()).
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);
        SupportMessage::create([
            'support_request_id' => $thread->id, 'user_id' => $owner->id, 'message' => 'm',
        ]);
        $admin->notify(new NewSupportRequest($thread, $owner));
        $this->assertSame(1, $this->orphanRows());

        $this->actingAs($owner)->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSame(0, $this->orphanRows());
        $this->assertDatabaseCount('support_requests', 0);
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_account_deletion_keeps_unrelated_threads_notifications(): void
    {
        // The Eloquent-first delete must only sweep the departing user's
        // own threads — another user's thread and its notification survive.
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $mine = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'mine']);
        $theirs = SupportRequest::create(['user_id' => $other->id, 'subject' => 'theirs']);
        $admin->notify(new NewSupportRequest($mine, $owner));
        $admin->notify(new NewSupportRequest($theirs, $other));
        $this->assertSame(2, $this->orphanRows());

        $this->actingAs($owner)->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSame(1, $this->orphanRows());
        $survivor = DB::table('notifications')->sole();
        $this->assertStringContainsString('"support_request_id":'.$theirs->id, (string) $survivor->data);
    }
}
