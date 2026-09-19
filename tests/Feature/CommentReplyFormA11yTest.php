<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #410: the reply form under each comment was hidden with inline
 * display:none and toggled by swapping that property — with no focus movement,
 * no announcement, and no expanded state on the button.
 *
 * A keyboard user clicking "Trả lời" had to tab blind through the whole comment
 * to find the textarea, and clicking "Hủy" left the focus stranded inside the
 * region that had just been hidden.
 */
class CommentReplyFormA11yTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reply_button_announces_the_region_it_controls(): void
    {
        $html = $this->renderThread();

        $this->assertSame(
            1,
            preg_match('/<button[^>]*class="[^"]*reply-btn[^"]*"[^>]*>/i', $html, $m),
            'the reply button must render exactly once'
        );
        $tag = $m[0];

        $this->assertStringContainsString('aria-expanded="false"', $tag, 'the button must announce its collapsed state before the form is opened');
        $this->assertStringContainsString('aria-controls="reply-form-1"', $tag, 'the button must point at the region it controls');
    }

    public function test_the_reply_form_is_announced_when_opened(): void
    {
        $html = $this->renderThread();

        $this->assertSame(
            1,
            preg_match('/<div[^>]*id="reply-form-1"[^>]*>/i', $html, $m),
            'the reply form container must render exactly once'
        );
        $this->assertStringContainsString('role="group"', $m[0], 'an opened form must be an announced landmark');
        $this->assertStringContainsString('aria-label="Form trả lời bình luận"', $m[0], 'the landmark must be announced by name');

        // The live region has to ship server-side: the JS only writes text into
        // it, it does not create it. Empty on first render is correct.
        $this->assertStringContainsString('class="reply-status sr-only" role="status" aria-live="polite"', $html, 'a polite region must ship for the open/close announcement');
    }

    public function test_the_live_region_is_visually_hidden_but_not_silenced(): void
    {
        // Bootstrap's .sr-only is not loaded by this app, so the rule is shipped
        // in the partial's own style block. display:none would defeat the whole
        // point — it removes the region from the accessibility tree.
        $css = $this->partialStylesheet();

        $this->assertStringContainsString('.reply-status.sr-only', $css, 'the visually-hidden rule must ship with the partial');
        $this->assertMatchesRegularExpression(
            '/\.reply-status\.sr-only\s*\{[^}]*position\s*:\s*absolute/i',
            $css,
            'the region must be hidden by clipping, not by display:none'
        );
    }

    public function test_the_js_moves_focus_and_tracks_state(): void
    {
        $js = file_get_contents(base_path('resources/views/comments/_item.blade.php'));

        // Opening must land in the textarea of THIS comment, and closing must
        // return focus to the button — not strand it in the hidden region.
        $this->assertStringContainsString('textarea.focus()', $js, 'opening must move focus into the reply textarea');
        $this->assertStringContainsString('btn.focus()', $js, 'closing must return focus to the reply button');

        // The button's expanded state must be driven by the same handler, or it
        // drifts from the form's actual visibility.
        $this->assertStringContainsString("btn.setAttribute('aria-expanded', open ? 'true' : 'false')", $js, 'aria-expanded must track the open state');

        // The live region text is written by the same handler, not hardcoded.
        $this->assertStringContainsString('reply-status', $js, 'the handler must address the live region');
    }

    private function renderThread(): string
    {
        [$user, $alert] = $this->seedThread();
        Comment::create([
            'user_id' => $user->id,
            'alert_id' => $alert->id,
            'content' => 'Nội dung bình luận',
        ]);

        return $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();
    }

    private function partialStylesheet(): string
    {
        return file_get_contents(base_path('resources/views/comments/_item.blade.php'));
    }

    private function seedThread(): array
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Tiêu đề',
            'description' => 'Mô tả',
            'status' => 'approved',
            'type' => 'theft',
        ]);

        return [$user, $alert];
    }
}
