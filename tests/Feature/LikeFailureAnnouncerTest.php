<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #414: the two like clients (alerts_show.js, experiences_show.js)
 * surfaced every failure through a raw alert() — a blocking, styleless,
 * semantics-free dialog in English jargon ("JSON parse error"), that also
 * left the button looking dead because nothing else had visibly changed.
 *
 * The region ships server-side so a static-DOM reader sees it; the JS only
 * writes textContent into it. Bootstrap's .sr-only is not loaded by this
 * app, so the clip has to ship inline — display:none would remove the
 * region from the accessibility tree and undo the whole fix.
 */
class LikeFailureAnnouncerTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $scripts = [
        'public/js/alerts_show.js',
        'public/js/experiences_show.js',
    ];

    public function test_like_failures_are_announced_not_alerted(): void
    {
        foreach ($this->scripts as $path) {
            $js = $this->script($path);

            // A raw alert() is a blocking modal with no semantics, and the
            // three strings below were the only ones in either script.
            $this->assertStringNotContainsString(
                "alert('Có lỗi xảy ra! (JSON parse error)')",
                $js,
                $path.' must not surface a JSON parse failure through a raw alert()'
            );
            $this->assertStringNotContainsString(
                "alert('Có lỗi xảy ra! (API error)')",
                $js,
                $path.' must not surface an API failure through a raw alert()'
            );
            $this->assertStringNotContainsString(
                "alert('Có lỗi xảy ra! (JS error)')",
                $js,
                $path.' must not surface a JS throw through a raw alert()'
            );

            $this->assertStringContainsString(
                'announceLikeFailure(',
                $js,
                $path.' must route every failure through the shared announcer'
            );
        }
    }

    public function test_the_announcer_addresses_the_shared_live_region(): void
    {
        foreach ($this->scripts as $path) {
            $this->assertStringContainsString(
                "querySelector('.like-status')",
                $this->script($path),
                $path.' must find the region the blade ships next to the button'
            );
        }
    }

    public function test_the_live_region_ships_server_side_on_both_pages(): void
    {
        $html = $this->renderAlertLikeButton();

        $this->assertSame(
            1,
            preg_match('/<span[^>]*class="[^"]*like-status[^"]*"[^>]*>/i', $html, $m),
            'exactly one like-status region must render on the alert page'
        );
        $this->assertStringContainsString('role="status"', $m[0], 'the region must be an ARIA status');
        $this->assertStringContainsString('aria-live="polite"', $m[0], 'the region must announce politely');
    }

    public function test_the_region_is_clipped_not_hidden_on_both_pages(): void
    {
        foreach (['alerts/show.blade.php', 'experiences/show.blade.php'] as $page) {
            $blade = $this->script('resources/views/'.$page);

            $this->assertSame(
                1,
                preg_match('/<span[^>]*class="[^"]*like-status[^"]*"[^>]*>/i', $blade, $m),
                $page.' must ship exactly one like-status region'
            );
            $tag = $m[0];

            // display:none would silence the region and defeat the fix. The
            // assertion is scoped to the region's own tag: the share popup on
            // the same page legitimately starts life hidden, and a comment in
            // the blade mentions the very rule being tested.
            $this->assertStringNotContainsString(
                'display:none',
                $tag,
                $page.' must not hide the region by removing it from the accessibility tree'
            );
            $this->assertStringContainsString(
                'clip:rect(0,0,0,0)',
                $tag,
                $page.' must clip the region because Bootstrap .sr-only is not loaded'
            );
        }
    }

    private function script(string $path): string
    {
        return file_get_contents(base_path($path));
    }

    private function renderAlertLikeButton(): string
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Tiêu đề',
            'description' => 'Mô tả',
            'status' => 'approved',
            'type' => 'theft',
        ]);

        return $this->actingAs($user)
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();
    }
}
