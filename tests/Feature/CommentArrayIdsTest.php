<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #161: the three post-target rules on POST /comments were
 * 'nullable|exists:...' with no type rule, and validateExists explicitly
 * ACCEPTS arrays (it counts array_unique values against the matching rows).
 * So alert_id=[<validId>] / experience_id=[<validId>] validated clean and
 * the raw array reached Comment::create ("Array to string conversion" ->
 * 500 inside the transaction), and parent_id=[<validId>] reached
 * Comment::findOrFail, which returns a Collection for array keys whose
 * ->alert_id deref threw 'Property [id] does not exist on this collection
 * instance' -> 500. Fix: 'integer' on all three, so the array is rejected
 * by a validation error (302 back / 422 JSON) before anything downstream
 * touches it.
 */
class CommentArrayIdsTest extends TestCase
{
    use RefreshDatabase;

    private function commenter(): User
    {
        return User::factory()->create();
    }

    public function test_array_alert_id_is_rejected_not_500(): void
    {
        // Pre-fix: the valid id passed 'exists' as an array and the
        // transaction insert threw "Array to string conversion" -> 500.
        $visitor = $this->commenter();
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($visitor)
            ->post('/comments', ['content' => 'hi', 'alert_id' => [$alert->id]])
            ->assertStatus(302)
            ->assertSessionHasErrors('alert_id');

        $this->assertSame(0, Comment::count());
    }

    public function test_array_experience_id_is_rejected_not_500(): void
    {
        $visitor = $this->commenter();
        $owner = User::factory()->create();
        $experience = Experience::create(['user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'approved']);

        $this->actingAs($visitor)
            ->post('/comments', ['content' => 'hi', 'experience_id' => [$experience->id]])
            ->assertStatus(302)
            ->assertSessionHasErrors('experience_id');

        $this->assertSame(0, Comment::count());
    }

    public function test_array_parent_id_is_rejected_not_500(): void
    {
        // Pre-fix: findOrFail(array) returned a Collection and $parent->id
        // threw the property-does-not-exist error -> 500.
        $visitor = $this->commenter();
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $parent = Comment::create(['alert_id' => $alert->id, 'user_id' => $author->id, 'content' => 'top']);

        $this->actingAs($visitor)
            ->post('/comments', ['content' => 'reply', 'parent_id' => [$parent->id], 'alert_id' => $alert->id])
            ->assertStatus(302)
            ->assertSessionHasErrors('parent_id');

        $this->assertSame(1, Comment::count()); // only the untouched parent
    }

    public function test_array_alert_id_json_gets_422(): void
    {
        $visitor = $this->commenter();
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($visitor)
            ->postJson('/comments', ['content' => 'hi', 'alert_id' => [$alert->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alert_id');
    }

    public function test_scalar_ids_still_comment_and_reply(): void
    {
        // Controls: 'integer' must not break the normal top-level flow nor
        // the reply flow (both exercised verbatim by CommentStoreRaceTest's
        // positive controls too; these pin the validation rules themselves).
        $visitor = $this->commenter();
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($visitor)
            ->post('/comments', ['content' => 'fine', 'alert_id' => $alert->id])
            ->assertRedirect();
        $top = Comment::firstWhere('content', 'fine');
        $this->assertNotNull($top);

        $this->actingAs($visitor)
            ->post('/comments', ['content' => 'reply', 'parent_id' => $top->id])
            ->assertRedirect();
        $this->assertSame(2, Comment::count());
    }
}
