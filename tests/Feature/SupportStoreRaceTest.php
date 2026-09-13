<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #164: store() created the thread and its opening message as two
 * autocommitted inserts, so an admin deleting the fresh thread (from the
 * near-instant /admin/support queue) before the second insert made the late
 * SupportMessage::create an uncaught FK violation (MySQL 1452 / SQLite
 * "FOREIGN KEY constraint failed" -> 500, submission lost), and on a
 * FK-disabled backend the same interleaving left an empty orphan thread —
 * the #53/#57 orphan-row class. Reproduced with the repo's own race idiom
 * (LikeTest #43/#139, CommentStoreRaceTest #153): a SupportMessage hook runs
 * a raw delete of the thread row inside the insert window. The fix wraps
 * both creates and the admin fan-out in one DB::transaction with a narrow
 * FK catch plus a post-insert current re-read that throws to roll the rows
 * back and back out before any notification fires.
 */
class SupportStoreRaceTest extends TestCase
{
    use RefreshDatabase;

    private function supportBells(): int
    {
        return DB::table('notifications')->where('type', NewSupportRequest::class)->count();
    }

    public function test_thread_deleted_between_the_two_inserts_backs_out_clean(): void
    {
        // The delete lands after SupportRequest::create and before the
        // message insert — pre-fix: uncaught QueryException 500 (FK on) or an
        // empty orphan thread (FK off). Post-fix the whole transaction rolls
        // back: neither row survives, and the user gets the error-flash shape
        // the #129 gate already uses instead of a 500.
        $user = User::factory()->create();
        User::factory()->admin()->create();

        SupportMessage::creating(function () use ($user) {
            // Simulates the admin's DELETE between the controller's two
            // creates: the request row is gone the moment the message insert
            // runs. Raw delete so the #102 model-event sweep never masks the
            // FK violation the controller must catch.
            DB::table('support_requests')->where('user_id', $user->id)->delete();
        });

        $this->actingAs($user)
            ->from('/support/create')
            ->post('/support', ['subject' => 'Help', 'message' => 'race me'])
            ->assertRedirect('/support/create')
            ->assertSessionHas('error');

        $this->assertSame(0, SupportRequest::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, $this->supportBells()); // no bell fan-out either
    }

    public function test_thread_deleted_after_the_message_insert_still_backs_out(): void
    {
        // The other half of the window: both rows write fine, then the thread
        // dies before the notify loop. Pre-fix this returns success and fires
        // admin bells pointing at a dead thread (the #102 sweep only runs via
        // the model delete event, which here already happened outside the
        // insert path — the raw delete below mimics a hard purge).
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        SupportMessage::created(function () use ($user) {
            DB::table('support_requests')->where('user_id', $user->id)->delete();
        });

        $this->actingAs($user)
            ->from('/support/create')
            ->post('/support', ['subject' => 'Help', 'message' => 'late delete'])
            ->assertRedirect('/support/create')
            ->assertSessionHas('error');

        // Post-insert current re-read threw -> transaction rollback: no
        // half-submission survives and no bell was ever queued.
        $this->assertSame(0, SupportRequest::count());
        $this->assertSame(0, SupportMessage::count());
        $this->assertSame(0, $this->supportBells());
    }

    public function test_normal_submission_still_lands_both_rows_and_bells(): void
    {
        // Control: the transaction must be invisible on the happy path —
        // thread + opening message + one bell per admin.
        $user = User::factory()->create();
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();

        $response = $this->actingAs($user)
            ->post('/support', ['subject' => 'Real', 'message' => 'a genuine question']);

        $thread = SupportRequest::sole();
        $this->assertSame('Real', $thread->subject);
        $response->assertRedirect(route('support.show', $thread));
        $msg = SupportMessage::sole();
        $this->assertSame($thread->id, $msg->support_request_id);
        $this->assertSame('a genuine question', $msg->message);
        $this->assertSame(2, $this->supportBells());
        $this->assertGreaterThan(0, DB::table('notifications')->where('notifiable_id', $admin1->id)->count());
        $this->assertGreaterThan(0, DB::table('notifications')->where('notifiable_id', $admin2->id)->count());
    }
}
