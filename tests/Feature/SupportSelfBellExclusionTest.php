<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #248: both support fan-outs lumped the ACTOR into the recipients.
 * store() rang NewSupportRequest for every admin INCLUDING a filer who is
 * himself an admin (support routes are bare auth — an admin can open a
 * thread, and authorizeViewer lets him view/answer his own), and
 * sendMessage()'s admin branch rang NewSupportMessage at $after->user even
 * when that WAS the sender replying to his own thread. The codebase already
 * states the contract — CommentController's reply fan-out: "chỉ gửi cho chủ
 * comment cha (nếu khác người gửi)" — and the alert/experience fan-outs are
 * structurally immune by branching on isAdmin. Both bells are now
 * actor-excluded; the legitimate shapes (admin answers a user's thread,
 * user opens/answers) must be untouched.
 */
class SupportSelfBellExclusionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return int number of $type rows addressed to $user
     */
    private function bellsFor(User $user, string $type): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->where('type', $type)
            ->count();
    }

    public function test_an_admin_filer_is_not_belled_for_the_thread_he_opened(): void
    {
        $filer = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $plain = User::factory()->create();

        $this->actingAs($filer)->post(route('support.store'), [
            'subject' => 'Admin cần hỗ trợ',
            'message' => 'Nội dung mở thread',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->bellsFor($other, NewSupportRequest::class),
            'the other admin still gets the bell');
        $this->assertSame(0, $this->bellsFor($filer, NewSupportRequest::class),
            'the actor must not be bell\'d for his own action');
        $this->assertSame(0, $this->bellsFor($plain, NewSupportRequest::class),
            'non-admins are not recipients of this fan-out');
    }

    public function test_a_regular_user_opening_a_thread_still_bells_every_admin(): void
    {
        // The exclusion must not shave the legitimate shape: a plain user
        // is never in the admin set, so every admin rings as before.
        $user = User::factory()->create();
        $a1 = User::factory()->admin()->create();
        $a2 = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('support.store'), [
            'subject' => 'Người dùng cần hỗ trợ',
            'message' => 'Nội dung mở thread',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->bellsFor($a1, NewSupportRequest::class));
        $this->assertSame(1, $this->bellsFor($a2, NewSupportRequest::class));
    }

    public function test_admin_reply_to_a_users_thread_still_bells_the_owner(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 's', 'status' => 'open']);

        $this->actingAs($admin)->post(route('support.sendMessage', $thread), [
            'message' => 'Admin trả lời user',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->bellsFor($owner, NewSupportMessage::class),
            'the legitimate admin->owner bell is unchanged');
    }

    public function test_admin_reply_to_his_own_thread_bells_no_one(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => $admin->id, 'subject' => 's', 'status' => 'open']);

        $this->actingAs($admin)->post(route('support.sendMessage', $thread), [
            'message' => 'Admin tự trả lời thread của mình',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->bellsFor($admin, NewSupportMessage::class),
            'the message stays (posted successfully), but no self-bell');
        $this->assertSame(1, DB::table('support_messages')->where('support_request_id', $thread->id)->count(),
            'the reply itself persists — the fix silences the bell, not the message');
    }
}
