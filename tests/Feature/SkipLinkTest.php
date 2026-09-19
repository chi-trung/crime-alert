<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #404: the app had no skip link and its <main> element carried neither
 * an id nor a tabindex, so a keyboard user was forced to tab through the whole
 * navigation on every page load before reaching any content.
 *
 * The nav is long: three primary links, the category dropdown, up to three
 * admin-only links, the notification bell with its own dropdown, and the
 * profile dropdown. That is a Level A failure (WCAG 2.4.1).
 *
 * The fix is two-sided: the link must render with an href that resolves to a
 * real focusable target, and the target must exist. A skip link pointing at a
 * missing id is worse than none — it silently does nothing when activated.
 */
class SkipLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_layout_ships_a_working_skip_link(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*href="#main-content"[^>]*class="skip-link"[^>]*>([^<]+)<\/a>/i',
            $html,
            'the skip link must render before the navigation'
        );
        preg_match('/<a[^>]*href="#main-content"[^>]*class="skip-link"[^>]*>([^<]+)<\/a>/i', $html, $m);
        $this->assertNotSame('', trim($m[1]), 'the skip link must carry visible text, not be an empty anchor');

        // The target must actually exist on the same page — a skip link to a
        // missing id is a no-op when activated.
        $this->assertMatchesRegularExpression(
            '/<main[^>]*id="main-content"[^>]*>/i',
            $html,
            'the main landmark must be the skip link target'
        );
        preg_match('/<main[^>]*id="main-content"[^>]*>/i', $html, $main);
        $this->assertStringContainsString('tabindex="-1"', $main[0], 'main must be programmatically focusable without joining the tab order');
    }

    public function test_the_skip_link_precedes_the_navigation(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $skipPos = strpos($html, 'class="skip-link"');
        $navPos = strpos($html, 'class="modern-nav"');

        $this->assertNotFalse($skipPos, 'the skip link must render');
        $this->assertNotFalse($navPos, 'the navigation must render');
        $this->assertLessThan(
            $navPos,
            $skipPos,
            'the skip link must come before the navigation in DOM order — it is the first tab stop on the page'
        );
    }

    public function test_the_skip_link_css_is_reachable_when_focused(): void
    {
        $css = $this->pageStylesheet();

        // The layout ships many rules that legitimately use display:none (the
        // notification dropdown, the chatbot window). The contract here is only
        // about the skip-link rules, so pull those out of the sheet and assert
        // against them in isolation — a blanket check over the whole page CSS
        // fails on unrelated rules and proves nothing about the link.
        preg_match_all('/\.skip-link[^{}]*\{[^}]*\}/s', $css, $rules);
        $this->assertNotEmpty($rules[0], 'the skip-link rules must ship in the page CSS');
        $skipCss = implode("\n", $rules[0]);

        // display:none would drop the link from the tab order and undo the
        // fix, so the off-screen technique must be position-based.
        $this->assertStringContainsString('position: absolute', $skipCss, 'the link is hidden off-screen, not removed from the tab order');
        $this->assertStringNotContainsString('display: none', $skipCss, 'display:none would make the link unreachable by keyboard');

        // The reveal rule is the point of the pattern: without it the link
        // stays off-screen forever, even when focused.
        $this->assertMatchesRegularExpression(
            '/\.skip-link\s*:focus\s*\{[^}]*left\s*:\s*0/i',
            $skipCss,
            'the link must move on-screen when it receives focus'
        );
    }

    private function pageStylesheet(): string
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        // The layout ships more than one <style> block; the skip-link rule can
        // live in any of them, so reassemble them all before asserting.
        preg_match_all('/<style>(.*?)<\/style>/s', $html, $m);
        $this->assertNotEmpty($m[1], 'the layout must ship inline style blocks');

        return implode("\n", $m[1]);
    }
}
