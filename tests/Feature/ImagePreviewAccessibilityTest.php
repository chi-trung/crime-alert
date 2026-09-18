<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #368: three accessibility defects in the alert image flow. Every
 * server-rendered <img> in this repo carries alt; the JS-built preview was the
 * only one that did not.
 *
 * 1. public/js/alerts_create.js builds the preview <img> without an alt, while
 *    the remove button two statements later does set aria-label.
 * 2. resources/views/alerts/edit.blade.php's remove-image button is glyph-only
 *    with no aria-label, unlike the create-page button.
 * 3. The edit page never loaded alerts_create.js, so setupImagePreview() never
 *    ran there: selecting a replacement file showed nothing until submit —
 *    editing blind, the same shape as the #137 blind spot.
 */
class ImagePreviewAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function script(): string
    {
        $script = file_get_contents(public_path('js/alerts_create.js'));
        $this->assertNotEmpty($script, 'alerts_create.js must exist and be non-empty');

        return $script;
    }

    public function test_the_preview_image_gets_an_alt(): void
    {
        $js = $this->script();

        // Strip comments so an explanatory note cannot satisfy the assertion.
        $withoutComments = preg_replace('/^[ \t]*\/\/.*$/m', '', $js);

        $this->assertStringContainsString(
            "img.alt = 'Ảnh xem trước'",
            $withoutComments,
            'the JS-built preview image must carry an alt'
        );
    }

    public function test_the_preview_image_is_still_wired_to_the_input(): void
    {
        // Positive control: the alt must not come at the cost of the wiring.
        $js = $this->script();

        $this->assertStringContainsString("getElementById('image')", $js);
        $this->assertStringContainsString("getElementById('image-preview')", $js);
        $this->assertStringContainsString('readAsDataURL', $js);
        $this->assertStringContainsString("className = 'img-fluid rounded-3 shadow-sm border'", $js);
    }

    public function test_the_create_remove_button_keeps_its_label(): void
    {
        // The label the edit button now mirrors; pin it so the shared wording
        // cannot drift between the two pages.
        $js = $this->script();

        $this->assertStringContainsString(
            'aria-label',
            $js,
            'the create-page remove button must keep its aria-label'
        );
    }

    public function test_the_edit_remove_button_has_an_aria_label(): void
    {
        $html = $this->editPage();

        // Scoped to the remove button only: the file input and the form own
        // their own labels and are not affected by this fix.
        $this->assertStringContainsString(
            'remove-image-btn" style="z-index:10;" aria-label="Bỏ ảnh này"',
            $html,
            'the glyph-only remove button must announce a Vietnamese label'
        );
    }

    public function test_the_edit_page_loads_the_preview_script(): void
    {
        // Without this include, setupImagePreview() never runs on the edit
        // page and a replacement file renders nothing until submit.
        $html = $this->editPage();

        $this->assertStringContainsString(
            'js/alerts_create.js',
            $html,
            'the edit page must load the same preview script as the create page'
        );
    }

    public function test_the_edit_page_has_somewhere_to_render_the_preview(): void
    {
        // setupImagePreview() bails when #image-preview is absent, so the div
        // has to ship even when no stored image exists yet.
        $html = $this->editPage();

        $this->assertStringContainsString(
            'id="image-preview"',
            $html,
            'the edit page must render the preview container'
        );
    }

    public function test_the_create_page_still_renders_its_preview_container(): void
    {
        // Regression guard for the page the script was written for.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get('/alerts/create')->assertOk()->getContent();

        $this->assertStringContainsString('id="image-preview"', $html);
        $this->assertStringContainsString('js/alerts_create.js', $html);
    }

    private function editPage(): string
    {
        // The edit form is reached by the owner or an admin; the owner is the
        // simpler case and is what the #137 stored-image markup guards.
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        // No AlertFactory exists in this repo; the required columns match the
        // shape EditAlertMapAssetTest uses, plus the stored image whose remove
        // button and preview the assertions below read.
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Cướp giật tại ngã tư',
            'description' => 'Mô tả sự việc.',
            'type' => 'Cướp giật',
            'status' => 'approved',
            'location' => 'Ngã tư B, Quận 1',
            'latitude' => 10.7769,
            'longitude' => 106.7009,
            'image' => 'alerts/test.jpg',
        ]);

        return $this->actingAs($user)
            ->get('/alerts/'.$alert->id.'/edit')
            ->assertOk()
            ->getContent();
    }
}
