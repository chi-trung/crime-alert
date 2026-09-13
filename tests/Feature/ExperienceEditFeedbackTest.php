<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #151: edit.blade.php:16 rendered the title error with
 * class="in   valid-feedback" — Bootstrap tokenizes that as two unknown
 * classes, so the block kept its base display:none and the user saw an
 * unexplained red input with no message. The content field below it
 * (line 21) always spelled the class correctly. These tests drive the
 * real overflow path (max:255 in ExperienceController::update) and pin
 * the corrected markup.
 */
class ExperienceEditFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_title_renders_a_visible_invalid_feedback_block(): void
    {
        $owner = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Na',
            'title' => 'Bai chia se',
            'content' => 'Noi dung',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Na',
                'title' => str_repeat('x', 256),
                'content' => 'Noi dung',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('title');

        $html = $this->actingAs($owner)->get("/experiences/{$experience->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('class="invalid-feedback"', $html);
        $this->assertStringNotContainsString('in   valid-feedback', $html);
        // The corrected div must sit in the title field, holding its
        // message (vi validation.php renders "Trường title ...").
        $this->assertMatchesRegularExpression(
            '/name="title".*?invalid-feedback[^>]*>[^<]*Trường title/is',
            $html,
            'title error div must be visible-eligible and carry the message'
        );
    }

    public function test_content_feedback_class_was_already_correct(): void
    {
        // Control for the asymmetry: line 21 was right all along, so a
        // content-only failure keeps rendering exactly one correct block.
        $owner = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Na',
            'title' => 'Bai chia se',
            'content' => 'Noi dung',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => 'Na',
                'title' => 'Ok',
                'content' => str_repeat('x', 10001),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('content');

        $html = $this->actingAs($owner)->get("/experiences/{$experience->id}/edit")->assertOk()->getContent();
        // Only the content @error block renders, and it was well-formed
        // before this fix — the control proves line 21 stays untouched.
        $this->assertSame(1, substr_count($html, 'class="invalid-feedback"'));
        $this->assertStringNotContainsString('in   valid-feedback', $html);
    }
}
