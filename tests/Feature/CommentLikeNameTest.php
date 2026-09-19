<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #408: the comment like button shipped only a heart glyph and a count,
 * so its accessible name was empty — a screen reader announced "button" with
 * no purpose. The same defect #388 closed for the neighbouring edit/delete
 * icons, which made this one an oversight rather than a design choice.
 *
 * The guest variant had it worse: only title=, which WCAG does not treat as an
 * accessible-name source. That branch renders solely on the public
 * experiences show page, because alerts.show is auth-gated.
 */
class CommentLikeNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_authenticated_like_button_is_named(): void
    {
        [$user, $alert, $comment] = $this->seedAlertThread();

        $html = $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        $tag = $this->captureLikeButton($html);
        $this->assertStringContainsString('aria-label="Thích bình luận"', $tag, 'an unliked comment must announce its purpose');
        $this->assertStringContainsString('aria-pressed="false"', $tag, 'the toggle state must be announced');
    }

    public function test_a_liked_comment_announces_the_unlike_action(): void
    {
        [$user, $alert, $comment] = $this->seedAlertThread();
        // Like::$fillable only allows user_id, so persist the morph pair
        // through the relation the same way LikeController does.
        $comment->likes()->create(['user_id' => $user->id]);

        $html = $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        $tag = $this->captureLikeButton($html);
        $this->assertStringContainsString('aria-label="Bỏ thích bình luận"', $tag, 'the label must follow the state, not stay static');
        $this->assertStringContainsString('aria-pressed="true"', $tag, 'a liked comment must announce the pressed state');
    }

    public function test_the_js_toggle_keeps_the_announced_state_in_step(): void
    {
        // The icon swap and the label swap must move together, or the glyph
        // shows a filled heart while the name still says "Thích".
        $js = file_get_contents(base_path('resources/views/comments/_item.blade.php'));

        $this->assertStringContainsString("btn.setAttribute('aria-pressed', liked ? 'false' : 'true')", $js, 'the toggle must update the pressed state');
        $this->assertStringContainsString("btn.setAttribute('aria-label', liked ? 'Thích bình luận' : 'Bỏ thích bình luận')", $js, 'the toggle must update the label');
    }

    public function test_the_guest_like_link_is_named(): void
    {
        // The guest branch renders only on the public experiences page, so seed
        // one real comment there or the row never renders and any assertion on
        // it would pass against nothing.
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => 'Tác giả',
            'title' => 'Tiêu đề',
            'content' => 'Nội dung',
            'status' => 'approved',
        ]);
        Comment::create([
            'user_id' => $user->id,
            'experience_id' => $experience->id,
            'content' => 'Nội dung bình luận',
        ]);

        $html = $this->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            preg_match('/<a[^>]*class="[^"]*btn-like-comment[^"]*"[^>]*>/i', $html, $m),
            'the guest like link must render exactly once'
        );
        $this->assertStringContainsString('aria-label="Đăng nhập để thích"', $m[0], 'title= is a tooltip, not an accessible name');
    }

    private function captureLikeButton(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<button[^>]*class="[^"]*btn-like-comment[^"]*"[^>]*>/i', $html, $m),
            'the like button must render exactly once'
        );

        return $m[0];
    }

    private function seedAlertThread(): array
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Tiêu đề',
            'description' => 'Mô tả',
            'status' => 'approved',
            'type' => 'theft',
        ]);
        $comment = Comment::create([
            'user_id' => $user->id,
            'alert_id' => $alert->id,
            'content' => 'Nội dung bình luận',
        ]);

        return [$user, $alert, $comment];
    }
}
