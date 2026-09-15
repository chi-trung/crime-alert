<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewReplyOnComment;
use App\Support\BellSweeps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Issue #311 (r15/notif-edges): #271 closed the orphan-bell window for the
 * SUPPORT thread destroy — the #121 notification sweep fires on the
 * deleting() hook BEFORE the DELETE statement acquires the target row's X
 * lock, so a fan-out transaction holding that lock (#153's lockForUpdate
 * target/parent current read, whose NewCommentOnPost/NewReplyOnComment rides
 * the synchronous database channel INSIDE that transaction) commits its bell
 * after the sweep already answered. notifications has no FK to the target
 * (the morph notifiable_* pair keys the RECIPIENT — #102's whole premise),
 * and with the row gone the hook can never re-sweep. AlertController::
 * destroy, ExperienceController::destroy and CommentController::destroy were
 * all bare $model->delete()s — the same window, unfixed on three routes.
 *
 * The alert/experience legs are PERMANENT orphans whose /alerts/N (or
 * /experiences/N) url 404s forever and which inflate the unread badge (no
 * janitor schedules the notifications table). The comment leg is bounded:
 * reply/comment bells url to the OWNING post plus a #comment fragment, so
 * the reachable wrong outcome is the badge counting a bell about a comment
 * that no longer exists, permanent until the post itself is later deleted.
 *
 * Closing, mirrored from #271/#307 per route: one DB::transaction whose
 * first statement is a lockForUpdate()->exists() probe that DECIDES (a
 * rival delete committing between the route binding's snapshot hydration
 * and this read 404s instead of flashing success — #307's doctrine, here
 * also verified on sqlite via the #163 retrieved-hook interleave), the
 * model delete, then a FIXED-POINT sweep of the notifications table AFTER
 * the delete through the shared App\Support\BellSweeps matcher — the same
 * method the #121 hooks now call, so hook and sweep cannot drift. MySQL
 * blocks the rival's fan-out behind the row lock until after the delete
 * (where its own #153 current reads back it out before the bell is written);
 * on sqlite compileLock is a no-op, so the fixed point is what closes it
 * there — the same dialect honesty #271 documents.
 *
 * The alert/experience routes additionally run the unlinking #289/#53 hook
 * inside a transaction for the first time, so #309's DeferredFileUnlinks
 * ledger is armed around that extent: the last two tests pin rollback-keeps-
 * file and commit-frees-file on the destroy route itself.
 */
class DestroyOrphanBellWindowTest extends TestCase
{
    use RefreshDatabase;

    private function rawPostBell(int $postId, string $postType, User $recipient, string $class = NewCommentOnPost::class): string
    {
        // Deliberately RAW (#163/#266/#271 rival idiom): the model/notify
        // pipeline would fire events and obscure the interleave. Payload
        // continues past post_id and post_type so the comma-delimited
        // matchers see exactly their real shape.
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => $class,
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'data' => json_encode([
                'comment_id' => 999,
                'post_id' => $postId,
                'post_type' => $postType,
                'url' => '/'.$postType.'s/'.$postId,
                'message' => 'committed after the sweep',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function rawReplyBell(int $commentId, int $postId, User $recipient): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => NewReplyOnComment::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $recipient->id,
            'data' => json_encode([
                'reply_id' => $commentId,
                'post_id' => $postId,
                'post_type' => 'alert',
                'url' => '/alerts/'.$postId.'#comment-'.$commentId,
                'message' => 'reply committed after the sweep',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_an_alert_bell_committed_after_the_hook_sweep_is_still_swept(): void
    {
        // The rival's fan-out bell landing in the pre-fix hook-vs-DELETE
        // gap. Temporary deleting() hook fires AFTER the booted #121 sweep
        // (booted hooks register first) — exactly the ordering the race
        // produces. Pre-fix (bare delete): the row goes, this bell stands
        // forever with a 404 url on the unread badge.
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        $this->rawPostBell($alert->id, 'alert', $recipient);
        $this->assertSame(1, DB::table('notifications')->count());

        Alert::deleting(function (Alert $a) use ($recipient): void {
            $this->rawPostBell($a->id, 'alert', $recipient);
        });

        $this->actingAs($owner)->delete(route('alerts.destroy', $alert))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('alerts', ['id' => $alert->id]);
        $this->assertSame(
            0,
            DB::table('notifications')->where('data', 'like', '%"post_id":'.$alert->id.',%')->count(),
            'the fixed-point sweep missed the late bell — permanent orphan whose url 404s'
        );
    }

    public function test_an_experience_bell_committed_after_the_hook_sweep_is_still_swept(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $experience = Experience::forceCreate([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 'T', 'content' => 'c', 'status' => 'approved',
        ]);

        Experience::deleting(function (Experience $e) use ($recipient): void {
            $this->rawPostBell($e->id, 'experience', $recipient, NewCommentOnPost::class);
        });

        $this->actingAs($owner)->delete(route('experiences.destroy', $experience))
            ->assertRedirect(route('experiences.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
        $this->assertSame(
            0,
            DB::table('notifications')->where('data', 'like', '%"post_id":'.$experience->id.',%')->count()
        );
    }

    public function test_a_reply_bell_committed_after_the_hook_sweep_is_still_swept(): void
    {
        // Comment leg: the bell urls at the OWNING post (alive) plus a dead
        // fragment, so the wrong outcome pinned here is the unread badge
        // counting a reply about a comment that no longer exists.
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $recipient->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        $comment = Comment::forceCreate(['user_id' => $owner->id, 'alert_id' => $alert->id, 'content' => 'c']);

        Comment::deleting(function (Comment $c) use ($alert): void {
            $this->rawReplyBell($c->id, $alert->id, $alert->user);
        });

        $this->actingAs($owner)->delete(route('comments.destroy', $comment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        // Owning post untouched...
        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
        // ...but its unread reply bell about the dead comment is gone.
        $this->assertSame(
            0,
            DB::table('notifications')->where('data', 'like', '%"reply_id":'.$comment->id.',%')->count()
        );
    }

    public function test_the_sweeps_keep_the_type_discriminator_and_the_comma_delimited_matcher(): void
    {
        // #102/#121's matcher rules, now load-bearing for the destroy-route
        // fixed point too: destroying alert 1 must not eat alert 11's bells
        // (prefix) nor experience 1's bells (id collision across tables).
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        // Explicit ids: the prefix case is only meaningful as 1-vs-11.
        $victim = Alert::forceCreate(['id' => 1, 'user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $neighbour = Alert::forceCreate(['id' => 11, 'user_id' => $owner->id, 'title' => 'B', 'description' => 'd', 'status' => 'approved']);
        $expTwin = Experience::forceCreate(['id' => 1, 'user_id' => $owner->id, 'name' => 'N', 'title' => 'T', 'content' => 'c', 'status' => 'approved']);

        $neighbourBell = $this->rawPostBell(11, 'alert', $recipient);
        $twinBell = $this->rawPostBell(1, 'experience', $recipient, NewPostNotification::class);

        Alert::deleting(function (Alert $a) use ($recipient): void {
            if ($a->id === 1) {
                // late bell for alert 1, which must still die
                $this->rawPostBell(1, 'alert', $recipient);
            }
        });

        $this->actingAs($owner)->delete(route('alerts.destroy', $victim));

        $this->assertSame(
            0,
            DB::table('notifications')->where('data', 'like', '%"post_id":1,%')->where('data', 'like', '%"post_type":"alert"%')->count()
        );
        $this->assertDatabaseHas('notifications', ['id' => $neighbourBell]);
        $this->assertDatabaseHas('notifications', ['id' => $twinBell]);
        $this->assertDatabaseHas('alerts', ['id' => $neighbour->id]);
    }

    public function test_an_alert_vanished_between_binding_and_transaction_404s_instead_of_flashing_success(): void
    {
        // #307's decided-probe on this route, sqlite-visible through #163's
        // retrieved-hook idiom: the route binding hydrates the model from
        // the snapshot, then a rival's DELETE commits before the controller
        // transaction. Pre-#311 this was a bare delete(): it touched 0 rows
        // and still redirected with 'Đã xoá cảnh báo!' — a false success
        // for a post this request did not delete.
        $owner = User::factory()->create();
        $alert = Alert::forceCreate(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $done = false;
        Alert::retrieved(function (Alert $a) use (&$done): void {
            if ($done) {
                return;
            }
            $done = true;
            DB::table('alerts')->where('id', $a->id)->delete();
        });

        $this->actingAs($owner)->delete(route('alerts.destroy', $alert))->assertNotFound();
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_an_experience_vanished_between_binding_and_transaction_404s(): void
    {
        $owner = User::factory()->create();
        $experience = Experience::forceCreate(['user_id' => $owner->id, 'name' => 'N', 'title' => 'T', 'content' => 'c', 'status' => 'approved']);

        $done = false;
        Experience::retrieved(function (Experience $e) use (&$done): void {
            if ($done) {
                return;
            }
            $done = true;
            DB::table('experiences')->where('id', $e->id)->delete();
        });

        $this->actingAs($owner)->delete(route('experiences.destroy', $experience))->assertNotFound();
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_a_comment_vanished_between_binding_and_transaction_404s(): void
    {
        $owner = User::factory()->create();
        $alert = Alert::forceCreate(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::forceCreate(['user_id' => $owner->id, 'alert_id' => $alert->id, 'content' => 'c']);

        $done = false;
        Comment::retrieved(function (Comment $c) use (&$done): void {
            if ($done) {
                return;
            }
            $done = true;
            DB::table('comments')->where('id', $c->id)->delete();
        });

        $this->actingAs($owner)->delete(route('comments.destroy', $comment))->assertNotFound();
        $this->assertArrayNotHasKey('success', session()->all());
    }

    public function test_a_rolled_back_alert_destroy_keeps_the_image_file(): void
    {
        // #309's ledger armed on this route: the transaction moves the hook
        // unlink inside a rollback-able scope for the first time, so a
        // rollback (here: a throw from a late deleting() hook, after the
        // #289 current read already captured the path) must leave the file
        // with its resurrected row. Pre-#311 there was no transaction at
        // all: the hook unlinked before the throw, and the surviving row
        // pointed at a dead file.
        Storage::fake('public');
        $owner = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd',
            'status' => 'approved', 'image' => 'alerts/a.png',
        ]);
        Storage::disk('public')->put('alerts/a.png', 'A');

        Alert::deleting(function (Alert $a) use ($owner): void {
            if ($a->user_id === $owner->id) {
                throw new RuntimeException('simulated late-hook failure');
            }
        });

        try {
            $this->withoutExceptionHandling();
            $this->actingAs($owner)->delete(route('alerts.destroy', $alert));
            $this->fail('the simulated hook failure must surface');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated late-hook failure', $e->getMessage());
        } finally {
            $this->withExceptionHandling();
        }

        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
        Storage::disk('public')->assertExists('alerts/a.png');
    }

    public function test_a_committed_alert_destroy_still_frees_the_image_file(): void
    {
        // The ledger must not become a silent leak on the happy path: the
        // drain after the commit unlinks exactly what the hook captured.
        Storage::fake('public');
        $owner = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $owner->id, 'title' => 'A', 'description' => 'd',
            'status' => 'approved', 'image' => 'alerts/a.png',
        ]);
        Storage::disk('public')->put('alerts/a.png', 'A');

        $this->actingAs($owner)->delete(route('alerts.destroy', $alert))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('alerts', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_shared_sweepers_stay_in_sync_with_the_hooks_class_lists(): void
    {
        // Property pin: Alert/Experience::deleting and Comment::deleting now
        // sweep through BellSweeps — the OLD inline lists are gone from the
        // models, and the class constellations here are the audit's ground
        // truth (six post classes; three comment classes). A refactor that
        // drops a class from BellSweeps without updating this pin fails.
        $this->assertEqualsCanonicalizing([
            'App\Notifications\NewPostNotification',
            'App\Notifications\NewPostPendingApprovalNotification',
            'App\Notifications\LikePostNotification',
            'App\Notifications\NewCommentOnPost',
            'App\Notifications\NewReplyOnComment',
            'App\Notifications\LikeCommentNotification',
        ], BellSweeps::POST_TYPES);
        $this->assertEqualsCanonicalizing([
            'App\Notifications\NewCommentOnPost',
            'App\Notifications\NewReplyOnComment',
            'App\Notifications\LikeCommentNotification',
        ], BellSweeps::COMMENT_TYPES);

        $this->assertStringNotContainsString('whereIn(\'type\'', file_get_contents(app_path('Models/Comment.php')));
        $this->assertStringNotContainsString('whereIn(\'type\'', file_get_contents(app_path('Models/Alert.php')));
        $this->assertStringNotContainsString('whereIn(\'type\'', file_get_contents(app_path('Models/Experience.php')));
    }
}
