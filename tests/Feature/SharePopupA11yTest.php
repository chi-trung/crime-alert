<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #398: the two "Chia sẻ" popups could be opened with a mouse but not
 * with a keyboard. There was no Escape handler (the only close path was a
 * click outside the popup), opening moved no focus anywhere and closing
 * returned none, and neither the trigger nor the popup carried any ARIA — so
 * a screen reader announced the button as a plain button and the popup as a
 * pile of unnamed divs. The logic was also duplicated: alerts_show.js held
 * toggleSharePopupAlert and experiences/show.blade.php held a near-identical
 * inline copy, the same two-places-one-bug shape as #351's login toggle.
 *
 * The fix moves the behaviour into one helper (public/js/share_popup.js) both
 * pages load. These tests pin the contract the helper depends on: the markup
 * attributes a screen reader needs must ship in the HTML itself, and the
 * duplicated inline script must stay gone — a page that silently regains an
 * inline copy would shadow the shared one again.
 */
class SharePopupA11yTest extends TestCase
{
    use RefreshDatabase;

    private function shareButtonRegex(string $id): string
    {
        return '/<button[^>]*id="'.$id.'"[^>]*>/i';
    }

    private function assertNamedTrigger(string $html, string $id, string $label): void
    {
        $this->assertMatchesRegularExpression(
            $this->shareButtonRegex($id),
            $html,
            "the {$label} share button must render"
        );
        preg_match($this->shareButtonRegex($id), $html, $m);
        $button = $m[0];
        $this->assertStringContainsString('aria-haspopup="true"', $button, "the {$label} trigger must announce it opens a popup");
        $this->assertStringContainsString('aria-expanded="false"', $button, "the {$label} trigger must announce its collapsed state");
        $this->assertStringContainsString('aria-label="', $button, "the {$label} trigger must keep its accessible name");
    }

    private function assertNamedPopup(string $html, string $id, string $label): void
    {
        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="'.$id.'"[^>]*>/i',
            $html,
            "the {$label} share popup must render"
        );
        preg_match('/<div[^>]*id="'.$id.'"[^>]*>/i', $html, $m);
        $this->assertStringContainsString('role="dialog"', $m[0], "the {$label} popup must be announced as a dialog");
        $this->assertStringContainsString('aria-label="Chia sẻ"', $m[0], "the {$label} popup must be announced by name");
    }

    public function test_the_alert_share_popup_is_keyboard_accessible(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Một cảnh báo',
            'description' => 'Mô tả.',
            'status' => 'approved',
            'type' => 'Trộm cắp',
        ]);

        $html = $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        $this->assertNamedTrigger($html, 'share-btn-alert', 'alert');
        $this->assertNamedPopup($html, 'share-popup-alert', 'alert');

        // Issue #398: the helper is what makes the trigger actually do
        // something. The onclick contract is gone with the duplicate.
        $this->assertStringNotContainsString('toggleSharePopupAlert', $html, 'the duplicated inline toggle must stay deleted');
        $this->assertStringContainsString('js/share_popup.js', $html, 'the shared helper must be loaded');
    }

    public function test_the_experience_share_popup_is_keyboard_accessible(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => 'Người dùng',
            'title' => 'Một bài chia sẻ',
            'content' => 'Nội dung.',
            'status' => 'approved',
        ]);

        $html = $this->actingAs($user)
            ->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        $this->assertNamedTrigger($html, 'share-btn-exp', 'experience');
        $this->assertNamedPopup($html, 'share-popup-exp', 'experience');

        // Issue #398: this page used to carry the inline duplicate. The blade
        // is the page that regressed here before, so the negative assertion is
        // the one that keeps it from coming back.
        $this->assertStringNotContainsString('toggleSharePopupExp', $html, 'the duplicated inline toggle must stay deleted');
        $this->assertStringContainsString('js/share_popup.js', $html, 'the shared helper must be loaded');
    }

    /**
     * The helper sets the same attributes at runtime as a safety net, but the
     * markup is the real contract — a blade that ships without them renders
     * into an unnamed button before the script runs. Pin that the helper is
     * what both pages reach for, and that it actually implements Escape and
     * focus rather than just the attributes.
     */
    public function test_the_shared_helper_implements_escape_and_focus(): void
    {
        $path = public_path('js/share_popup.js');
        $this->assertFileExists($path);
        $js = file_get_contents($path);

        $this->assertStringContainsString("e.key === 'Escape'", $js, 'the helper must close the popup on Escape');
        $this->assertStringContainsString('.focus()', $js, 'the helper must move focus into the popup and back to the trigger');
        $this->assertStringContainsString('aria-expanded', $js, 'the helper must keep the trigger state in sync');
        $this->assertStringContainsString('role', $js, 'the helper must repair missing dialog semantics at runtime');

        // Two pages, one implementation: the pages must not carry their own
        // copies of the toggle anymore.
        foreach (['alerts_show.js', 'experiences_show.js'] as $file) {
            $this->assertStringNotContainsString(
                'function toggleSharePopup',
                file_get_contents(public_path('js/'.$file)),
                "{$file} must delegate to the shared helper"
            );
        }
    }
}
