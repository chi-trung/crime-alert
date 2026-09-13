<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #163: sendMessage()'s open-status gate, the SupportMessage::create,
 * and the counterpart/admin fan-out were separate autocommitted statements,
 * so an admin closing the thread in the binding->insert window landed a
 * message row and a full bell fan-out in a now-closed thread (silently
 * defeating the closed-thread invariant #98/#141 keep), and a destroy
 * committed in that window turned the insert into an uncaught FK violation
 * (500). A delete landing after the row but before the notify rang bells
 * whose support_request_id payload pointed at a dead thread (the #102
 * orphan class). Reproduced with the repo's race idiom (LikeTest #43/#139,
 * CommentStoreRaceTest #153): model hooks perform the rival admin action
 * inside the gate-to-insert / insert-to-notify window. The fix wraps the
 * whole sequence in a DB::transaction whose lockForUpdate current re-reads
 * re-run the status gate on the authoritative row and back out before any
 * notification fires, with the same answers a pre-race request gets (404
 * for a vanished thread, the existing closed-thread error flash for a raced
 * close).
 */
class SupportSendMessageRaceTest extends TestCase
{
    use RefreshDatabase;

    private function messageBells(): int
    {
        return DB::table('notifications')->where('type', NewSupportMessage::class)->count();
    }

    public function test_close_committed_mid_send_lands_no_message_or_bell(): void
    {
        // The admin's close (a model update, so no FK is involved) lands
        // after the snapshot status gate and before the insert. Pre-fix: the
        // message row and every admin bell survive inside the closed thread.
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);

        SupportMessage::creating(function () use ($thread) {
            // Deliberately raw: an Eloquent save would fire the model events
            // and the #102 sweep. Note where('id', ...) rather than
            // whereKey — the query builder has no whereKey, and its __call
            // would silently read it as a dynamic filter on a "key" column,
            // turning the race into a no-op.
            DB::table('support_requests')->where('id', $thread->id)->update(['status' => 'closed']);
        });

        $this->actingAs($owner)
            ->from(route('support.show', $thread))
            ->post(route('support.sendMessage', $thread), ['message' => 'late'])
            ->assertRedirect(route('support.show', $thread))
            ->assertSessionHas('error');

        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, $this->messageBells());
    }

    public function test_destroy_committed_mid_send_404s_not_500(): void
    {
        // The thread's row vanishes after the current read and before the
        // insert — pre-fix the FK rejected the late insert: uncaught
        // QueryException / HTTP 500. Post-fix the narrow FK catch backs out
        // with the 404 the route binding itself would answer for an
        // already-deleted thread.
        $owner = User::factory()->create();
        User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);

        SupportMessage::creating(function () use ($thread) {
            DB::table('support_requests')->where('id', $thread->id)->delete();
        });

        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => 'race'])
            ->assertNotFound();

        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, $this->messageBells());
    }

    public function test_thread_deleted_after_the_row_still_backs_out_before_bells(): void
    {
        // The other half of the window: the row writes fine, then the thread
        // dies before the notify loop. Pre-fix the fan-out rang bells whose
        // payload urls 404 and whose support_request_id points at a dead
        // thread (the #102 class recursing). Post-fix the post-insert current
        // read erases the message and backs out to 404 with zero bells.
        $owner = User::factory()->create();
        User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);

        SupportMessage::created(function () use ($thread) {
            DB::table('support_requests')->where('id', $thread->id)->delete();
        });

        $this->actingAs($owner)
            ->post(route('support.sendMessage', $thread), ['message' => 'ghost'])
            ->assertNotFound();

        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, $this->messageBells());
    }

    public function test_owner_reply_in_open_thread_still_lands_and_bells_admins(): void
    {
        // Control: the transaction must be invisible on the happy path — the
        // message row plus one bell per admin, thread untouched.
        $owner = User::factory()->create();
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);

        $this->actingAs($owner)
            ->from(route('support.show', $thread))
            ->post(route('support.sendMessage', $thread), ['message' => 'hello'])
            ->assertRedirect(route('support.show', $thread))
            ->assertSessionMissing('error');

        $msg = SupportMessage::sole();
        $this->assertSame('hello', $msg->message);
        $this->assertSame(2, $this->messageBells());
        $bell = DB::table('notifications')->where('notifiable_id', $admin1->id)->value('data');
        $this->assertStringContainsString('"support_request_id":'.$thread->id, (string) $bell);
    }

    public function test_admin_reply_still_notifies_only_the_owner(): void
    {
        // Control for the second fan-out arm (admin -> single owner bell), so
        // the restructured notify block keeps its routing choice.
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);

        $this->actingAs($admin)
            ->from(route('support.show', $thread))
            ->post(route('support.sendMessage', $thread), ['message' => 'answer'])
            ->assertRedirect(route('support.show', $thread));

        $this->assertSame(1, SupportMessage::count());
        $this->assertSame(1, $this->messageBells());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $otherAdmin->id)->count());
    }
}
