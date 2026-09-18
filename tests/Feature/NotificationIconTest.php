<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewReplyOnComment;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Issue #343: every notification row rendered the SAME icon (green
 * fa-comment-dots) regardless of what actually happened, so a reply, a like
 * and a support-ticket reply were visually indistinguishable in the list.
 * The fix branches on $notification->type — the resolved class name stored
 * at creation. These tests fire each real notification and pin the icon
 * the row renders, so a new notification class added without an entry in
 * the match() falls through to the default rather than inheriting some
 * other class's icon silently.
 */
class NotificationIconTest extends TestCase
{
    use RefreshDatabase;

    private function iconsFor(User $user): array
    {
        $html = $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->getContent();

        // Grab the fas classes of each row's icon cell in render order.
        preg_match_all('/<i class="fas ([\w-]+) ([\w-]+) fa-2x">/u', $html, $m);

        return $m[1];
    }

    private function userWithNotifications(int $count): User
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['user_id' => $other->id, 'alert_id' => $alert->id, 'content' => 'c']);

        // Every class that can land in the notifications table, one of each.
        $owner->notify(new NewPostNotification($alert, $other, 'alert'));
        $owner->notify(new NewPostPendingApprovalNotification($alert, $other, 'alert'));
        $owner->notify(new LikePostNotification($other, $alert, 'alert'));
        $owner->notify(new LikeCommentNotification($other, $comment, $alert, 'alert'));
        $owner->notify(new NewCommentOnPost($comment, $alert, $other, 'alert'));
        $owner->notify(new NewReplyOnComment($comment, $comment, $alert, 'alert'));

        // No SupportRequest factory in this repo (see CreateFanoutThrottleTest
        // for the established pattern); NewSupportMessage needs a first row
        // on the thread relation, so create both by hand.
        $support = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);
        $owner->notify(new NewSupportRequest($support, $other));

        $support->messages()->create(['user_id' => $other->id, 'message' => 'Reply body']);
        $owner->notify(new NewSupportMessage($support, $other, $support->messages()->first()));

        $this->assertSame($count, $owner->notifications()->count());

        return $owner;
    }

    public function test_each_notification_kind_renders_its_own_icon(): void
    {
        $owner = $this->userWithNotifications(8);
        $icons = $this->iconsFor($owner);

        // Eight rows, eight icons, six distinct classes — a reply no longer
        // looks identical to a like or a support reply.
        $this->assertCount(8, $icons);
        $this->assertCount(6, array_unique($icons));
    }

    public function test_icons_are_not_all_the_old_comment_dots(): void
    {
        // The pre-#343 single icon. If the match() were removed or
        // short-circuited, every entry here would collapse back to it.
        $icons = $this->iconsFor($this->userWithNotifications(8));

        $this->assertNotContains('fa-comment-dots', $icons);
    }

    public function test_like_notifications_render_the_heart_icon(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved']);

        $owner->notify(new LikePostNotification($other, $alert, 'alert'));

        $this->assertContains('fa-heart', $this->iconsFor($owner));
    }

    public function test_support_notifications_render_the_headset_icon(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $support = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);
        $owner->notify(new NewSupportRequest($support, $other));

        $this->assertContains('fa-headset', $this->iconsFor($owner));
    }

    public function test_an_unknown_notification_class_falls_through_to_a_default_icon(): void
    {
        // Rows whose type is not in the match() must still render — with the
        // default bullhorn, not a crash and not some other class's icon.
        $owner = User::factory()->create();

        $owner->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\SomeFutureClass',
            'data' => ['message' => 'future event'],
            'read_at' => null,
        ]);

        $this->assertContains('fa-bullhorn', $this->iconsFor($owner));
    }
}
