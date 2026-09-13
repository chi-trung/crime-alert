<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #143: comments/edit.blade.php built its "Quay lại" link as
 * route('alerts.show', $comment->alert_id) unconditionally. Experience
 * comments carry alert_id NULL, so for those the required {alert} param
 * never bound and the page 500'd with UrlGenerationException before
 * rendering — an author could never edit their own experience comment
 * through the UI (the edit button in _item.blade.php shows on experience
 * threads too). The view now branches on the owning post exactly like
 * CommentController::update()'s redirect.
 */
class CommentEditExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_for_an_experience_comment_renders_with_the_experience_back_link(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $author->id, 'name' => 'N', 'title' => 'T', 'content' => 'c', 'status' => 'approved',
        ]);
        $comment = Comment::create([
            'experience_id' => $experience->id, 'user_id' => $commenter->id, 'content' => 'c',
        ]);

        // Pre-fix: UrlGenerationException -> 500.
        $this->actingAs($commenter)
            ->get(route('comments.edit', $comment))
            ->assertOk()
            ->assertSee(route('experiences.show', $experience->id), false);
    }

    public function test_edit_page_for_an_alert_comment_still_links_back_to_the_alert(): void
    {
        // Control: the alert branch of the new ternary keeps the old target.
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $alert = Alert::create(['user_id' => $author->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $comment = Comment::create(['alert_id' => $alert->id, 'user_id' => $commenter->id, 'content' => 'c']);

        $this->actingAs($commenter)
            ->get(route('comments.edit', $comment))
            ->assertOk()
            ->assertSee(route('alerts.show', $alert->id), false);
    }
}
