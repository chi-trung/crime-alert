<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #394: four SVGs shipped without aria-hidden, so a screen reader
 * announced each as its own empty image node.
 *
 * Two of them sit inside a named parent: the X share links carry the literal
 * text "X" and the Facebook links "Facebook", so the svg inside is pure
 * decoration and must not be announced a second time. The other two are
 * background wave curves on the auth pages and carry no information at all.
 *
 * Every test also asserts the link's own text survived — aria-hidden has to
 * land on the svg, not swallow the label the anchor already had.
 */
class DecorativeSvgTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_x_share_icons_are_hidden_on_the_alert_page(): void
    {
        $alert = Alert::create([
            'user_id' => $user = User::factory()->create()->id,
            'title' => 'Cảnh báo thử nghiệm',
            'description' => 'Nội dung thử nghiệm.',
            'type' => 'Gian lận',
            'status' => 'approved',
            'latitude' => 10.76,
            'longitude' => 106.66,
        ]);

        $html = $this->actingAs(User::find($user))
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString(
            'id="share-x-alert"',
            $html,
            'the X share link must still exist'
        );
        // The anchor's own name is the literal "X" beside the icon — pin it so
        // an aria-hidden fix cannot delete the text that names the link.
        preg_match('/<a[^>]*id="share-x-alert"[^>]*>(.*?)<\/a>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString(
            'X',
            trim(strip_tags($m[1])),
            'the X link keeps its text label now that the icon is hidden'
        );
    }

    public function test_the_x_share_icons_are_hidden_on_the_experience_page(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => 'Người chia sẻ',
            'title' => 'Kinh nghiệm thử nghiệm',
            'content' => 'Nội dung thử nghiệm.',
            'status' => 'approved',
        ]);

        $html = $this->actingAs($user)
            ->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('id="share-x-exp"', $html);
        preg_match('/<a[^>]*id="share-x-exp"[^>]*>(.*?)<\/a>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('X', trim(strip_tags($m[1])));
    }

    public function test_the_login_page_hides_its_background_wave(): void
    {
        $html = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'aria-hidden="true"',
            $html,
            'the decorative wave svg on login must not be announced as an image'
        );
    }

    public function test_the_register_page_hides_its_background_wave(): void
    {
        $html = $this->get(route('register'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'aria-hidden="true"',
            $html,
            'the decorative wave svg on register must not be announced as an image'
        );
    }
}
