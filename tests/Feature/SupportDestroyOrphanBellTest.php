<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #271: SupportRequestController::destroy() ran a bare delete() —
 * the #102 notification sweep fires on the deleting() hook BEFORE the
 * DELETE statement acquires the thread row's X lock, so a sendMessage
 * transaction (which lockForUpdate()s the same row as its first
 * statement, #163) can commit its admin bell INTO that gap. The DELETE
 * then FK-cascades the message row but the morph notifications table has
 * no FK — that is the entire reason #102 exists — and with the thread row
 * gone the hook can never fire again: a permanently orphaned bell whose
 * url 404s and which inflates the unread badge. #163's own comment
 * named this residual ("closing that fully needs row locking in the
 * thread's own delete/close paths") — this fix is that closing, with
 * #266's lock-first + post-delete fixed-point doctrine.
 */
class SupportDestroyOrphanBellTest extends TestCase
{
    use RefreshDatabase;

    private function rawBell(int $threadId, User $admin, string $subject = 'Late'): void
    {
        // Deliberately RAW — writing through the model/notify pipeline in
        // the test would fire events and obscure the interleaving being
        // pinned (the #163/#266 rival idiom).
        \DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => NewSupportMessage::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $admin->id,
            'data' => json_encode([
                'support_request_id' => $threadId,
                'support_subject' => $subject,
                'sender_name' => 'Rival',
                'message' => 'committed after the sweep',
                'url' => '/support/'.$threadId,
                'type' => 'support',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_bell_committed_after_the_sweep_is_still_swept(): void
    {
        $admin = User::factory()->create(['isAdmin' => true]);
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Chết giữa sweep', 'status' => 'open']);

        // The rival's commit landing in the pre-fix window: the #102
        // deleting() hook has already run (booted hooks are registered
        // first, so this one fires AFTER it) but the DELETE has not
        // acquired the row lock on a busy backend.
        SupportRequest::deleting(function (SupportRequest $r) use ($admin): void {
            $this->rawBell($r->id, $admin);
        });

        $this->actingAs($admin)->from('/admin/support')->delete(route('admin.support.destroy', $thread));

        // Post-fix: destroy() locks the row first, deletes, and sweeps the
        // bell class again at the fixed point AFTER the delete — the
        // late-committed row is gone. Pre-fix it survives forever.
        $this->assertSame(
            0,
            \DB::table('notifications')->where('data', 'like', '%"support_request_id":'.$thread->id.',%')->count()
        );
        $this->assertDatabaseMissing('support_requests', ['id' => $thread->id]);
    }

    public function test_destroy_still_clears_thread_messages_and_pre_existing_bells(): void
    {
        $admin = User::factory()->create(['isAdmin' => true]);
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Thread đủ hồ sơ', 'status' => 'open']);
        SupportMessage::create(['support_request_id' => $thread->id, 'user_id' => $owner->id, 'message' => 'tin nhắn']);
        // Both support bell classes must fall to the sweep, as before.
        $owner->notify(new NewSupportRequest($thread, $owner));
        $admin->notify(new NewSupportMessage($thread, $thread->messages()->first(), $owner));
        $this->assertSame(2, \DB::table('notifications')->count());

        $this->actingAs($admin)->from('/admin/support')->delete(route('admin.support.destroy', $thread))
            ->assertRedirect('/admin/support')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('support_requests', ['id' => $thread->id]);
        $this->assertDatabaseMissing('support_messages', ['support_request_id' => $thread->id]);
        $this->assertSame(
            0,
            \DB::table('notifications')->where('data', 'like', '%"support_request_id":'.$thread->id.',%')->count()
        );
    }

    public function test_the_fixed_point_sweep_keeps_the_comma_delimited_matcher(): void
    {
        $admin = User::factory()->create(['isAdmin' => true]);
        $owner = User::factory()->create();
        $victim = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Nạn nhân', 'status' => 'open']);
        $neighbour = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Hàng xóm', 'status' => 'open']);

        // Bells for BOTH threads: the id prefix "victim" must not eat the
        // neighbour's rows — this is #102's comma-delimited matcher, and
        // the new post-delete sweep must not be sloppier than the hook.
        $this->rawBell($victim->id, $admin);
        $this->rawBell($neighbour->id, $admin);

        $this->actingAs($admin)->from('/admin/support')->delete(route('admin.support.destroy', $victim));

        $this->assertSame(
            0,
            \DB::table('notifications')->where('data', 'like', '%"support_request_id":'.$victim->id.',%')->count()
        );
        $this->assertSame(
            1,
            \DB::table('notifications')->where('data', 'like', '%"support_request_id":'.$neighbour->id.',%')->count()
        );
    }
}
