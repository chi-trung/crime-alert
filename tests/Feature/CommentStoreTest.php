<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #35: store() accepted parent_id without binding it to the submitted
 * post, so a crafted request could inject a reply into an arbitrary thread
 * or split the reply's parent from its owning post.
 */
class CommentStoreTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(); // verified by default
    }

    private function alert(User $owner): Alert
    {
        return Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
    }

    public function test_top_level_comment_still_requires_a_post(): void
    {
        $this->actingAs($this->user())->post('/comments', ['content' => 'orphan?'])
            ->assertSessionHasErrors('alert_id');

        $this->assertSame(0, Comment::count());
    }

    public function test_reply_inherits_its_post_from_the_parent_even_without_client_post_id(): void
    {
        $owner = $this->user();
        $alert = $this->alert($owner);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $owner->id, 'content' => 'top']);

        // The legitimate UI posts parent_id + alert_id; a minimal client may
        // omit the post id — the row must still land on the parent's alert.
        $this->actingAs($this->user())->post('/comments', [
            'content' => 'reply',
            'parent_id' => $parent->id,
        ])->assertSessionHasNoErrors();

        $reply = Comment::latest('id')->firstOrFail();
        $this->assertSame($parent->id, $reply->parent_id);
        $this->assertSame($alert->id, $reply->alert_id);
        $this->assertNull($reply->experience_id);
    }

    public function test_reply_with_mismatched_post_id_is_rejected(): void
    {
        $owner = $this->user();
        $parent = Comment::create(['alert_id' => $this->alert($owner)->id, 'user_id' => $owner->id, 'content' => 'top']);
        $other = $this->alert($owner);

        // Parent lives on one alert, client claims another: rejected outright,
        // no row created.
        $this->actingAs($this->user())->post('/comments', [
            'content' => 'inject',
            'parent_id' => $parent->id,
            'alert_id' => $other->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull(Comment::where('content', 'inject')->first());
    }

    public function test_reply_cannot_span_post_types(): void
    {
        $owner = $this->user();
        $parent = Comment::create(['alert_id' => $this->alert($owner)->id, 'user_id' => $owner->id, 'content' => 'top']);
        $experience = Experience::create([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'approved',
        ]);

        $this->actingAs($this->user())->post('/comments', [
            'content' => 'cross',
            'parent_id' => $parent->id,
            'experience_id' => $experience->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame(1, Comment::count()); // only the parent exists
    }

    public function test_unknown_parent_is_rejected(): void
    {
        $owner = $this->user();
        $alert = $this->alert($owner);

        $this->actingAs($this->user())->post('/comments', [
            'content' => 'ghost',
            'parent_id' => 99999,
            'alert_id' => $alert->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame(0, Comment::count());
    }
}
