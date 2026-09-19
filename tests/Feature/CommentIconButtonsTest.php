<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #388: the comment row carried two icon-only actions — edit and delete
 * — with no text and no aria-label. A screen reader announced "link" and
 * "button" with no purpose, and the second one is irreversible.
 */
class CommentIconButtonsTest extends TestCase
{
    use RefreshDatabase;

    private function commentRow(User $author, User $viewer): array
    {
        $alert = Alert::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Cảnh báo thử nghiệm',
            'description' => 'Mô tả.',
            'type' => 'theft',
            'status' => 'approved',
            'latitude' => 10.7626,
            'longitude' => 106.6602,
        ]);

        $comment = $alert->comments()->create([
            'user_id' => $author->id,
            'content' => 'Một bình luận.',
        ]);

        $html = $this->actingAs($viewer)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        return [$comment, $html];
    }

    public function test_the_owner_sees_labeled_edit_and_delete_actions(): void
    {
        $owner = User::factory()->create();
        [$comment, $html] = $this->commentRow($owner, $owner);

        $this->assertStringContainsString(
            'aria-label="Sửa bình luận',
            $html,
            'the edit link must carry an accessible name'
        );
        $this->assertStringContainsString(
            'aria-label="Xóa bình luận',
            $html,
            'the delete button must carry an accessible name — the action is irreversible'
        );
    }

    public function test_the_actions_stay_visible_to_an_admin(): void
    {
        $admin = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'isAdmin' => true,
        ])->save());

        [$comment, $html] = $this->commentRow(User::factory()->create(), $admin);

        $this->assertStringContainsString('aria-label="Sửa bình luận', $html);
        $this->assertStringContainsString('aria-label="Xóa bình luận', $html);
    }

    public function test_a_comment_visitor_does_not_see_the_actions(): void
    {
        $other = User::factory()->create();

        [$comment, $html] = $this->commentRow(User::factory()->create(), $other);

        $this->assertStringNotContainsString('aria-label="Sửa bình luận', $html);
        $this->assertStringNotContainsString('aria-label="Xóa bình luận', $html);
    }

    public function test_the_action_icons_do_not_announce_themselves(): void
    {
        $owner = User::factory()->create();
        [$comment, $html] = $this->commentRow($owner, $owner);

        // The icons are decoration now that the buttons are labelled; without
        // aria-hidden a screen reader reads them as separate image nodes.
        preg_match_all('/<a[^>]*aria-label="Sửa bình luận[^"]*"[^>]*>.*?<\/a>/s', $html, $editLinks);
        $this->assertNotEmpty($editLinks[0]);
        foreach ($editLinks[0] as $link) {
            $this->assertStringContainsStringIgnoringCase(
                'aria-hidden="true"',
                $link,
                'the edit icon must be hidden from the a11y tree'
            );
        }
    }
}
