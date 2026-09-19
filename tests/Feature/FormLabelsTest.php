<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #384: placeholders are not labels. A screen reader announces a
 * placeholder-only field by its placeholder text, which disappears the moment
 * the user types; a <label> without a for= is not bound to anything. This test
 * pins both halves — the for/id pairs and the aria-label fallback used where a
 * visible label would break the UI (chat bubbles, map inputs).
 */
class FormLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function assertFieldLabeled(string $html, string $id): void
    {
        $this->assertMatchesRegularExpression(
            '/<label[^>]*for="'.preg_quote($id, '/').'"/i',
            $html,
            "field id=\"{$id}\" must have a <label for=\"{$id}\">"
        );
        $this->assertMatchesRegularExpression(
            '/<(input|textarea|select)[^>]*id="'.preg_quote($id, '/').'"/i',
            $html,
            "label for=\"{$id}\" must point at a real field with that id"
        );
    }

    public function test_the_experience_create_form_labels_every_field(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get(route('experiences.create'))
            ->assertOk()
            ->getContent();

        // The authed branch renders the name field disabled (the server value
        // comes from the hidden input next to it), so it still needs a label.
        $this->assertFieldLabeled($html, 'experience-name');
        $this->assertFieldLabeled($html, 'experience-title');
        $this->assertFieldLabeled($html, 'experience-content');

        // Every label that is bound to a field must carry a for= — an unbound
        // <label> is decorative and announces nothing.
        preg_match_all('/<label\b[^>]*>/i', $html, $matches);
        foreach ($matches[0] as $label) {
            $this->assertStringContainsStringIgnoringCase(
                'for=',
                $label,
                'a label on the experience form must be bound with for=: '.$label
            );
        }
    }

    public function test_the_experience_edit_form_labels_every_field(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'title' => 'Một tiêu đề',
            'content' => 'Nội dung gốc.',
        ]);

        $html = $this->actingAs($user)
            ->get(route('experiences.edit', $experience))
            ->assertOk()
            ->getContent();

        $this->assertFieldLabeled($html, 'experience-name');
        $this->assertFieldLabeled($html, 'experience-title');
        $this->assertFieldLabeled($html, 'experience-content');

        preg_match_all('/<label\b[^>]*>/i', $html, $matches);
        foreach ($matches[0] as $label) {
            $this->assertStringContainsStringIgnoringCase('for=', $label);
        }
    }

    public function test_comment_boxes_carry_an_accessible_name(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Cảnh báo thử nghiệm',
            'description' => 'Mô tả.',
            'type' => 'theft',
            'status' => 'approved',
            'latitude' => 10.7626,
            'longitude' => 106.6602,
        ]);

        // alerts/show includes the comment list partial, whose reply boxes
        // appear once a comment exists.
        $alert->comments()->create(['user_id' => $user->id, 'content' => 'Bình luận đầu tiên.']);

        $html = $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        // The reply boxes live in the recursive partial, so an id would not be
        // unique — the accessible name has to come from aria-label instead.
        preg_match_all('/<textarea\b[^>]*name="content"[^>]*>/i', $html, $matches);
        $this->assertGreaterThanOrEqual(2, count($matches[0]), 'page must render the comment and reply boxes');
        foreach ($matches[0] as $textarea) {
            $this->assertStringContainsStringIgnoringCase(
                'aria-label',
                $textarea,
                'a placeholder-only textarea announces its placeholder as the field name: '.$textarea
            );
        }
    }

    public function test_the_wanted_list_search_box_is_labeled(): void
    {
        $html = $this->get(route('wanted_list.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*id="wanted-q"[^>]*aria-label=/i',
            $html,
            'the wanted-list search box must carry an accessible name'
        );
    }
}
