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
        // thread 11's rows. Ids forced apart deterministically.
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $first = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'a']);
        foreach (range(1, 9) as $i) {
            SupportRequest::create(['user_id' => $owner->id, 'subject' => "pad-{$i}"]);
        }
        $eleventh = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'b']);
        $this->assertSame(1, $first->id);
        $this->assertSame(11, $eleventh->id);

        $admin->notify(new NewSupportRequest($eleventh, $owner));
        $this->assertSame(1, $this->orphanRows());

        $first->delete();

        $this->assertSame(1, $this->orphanRows());
    }
}
