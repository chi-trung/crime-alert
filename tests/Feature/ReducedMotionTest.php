<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #416: no stylesheet in the project honoured
 * prefers-reduced-motion (WCAG 2.2.2 "Pause, Stop, Hide", Level A). The
 * welcome page alone ships four infinite animations — a 15s gradient shift,
 * a 20s/25s/30s particle drift and a 3s title glow — so a user with motion
 * sensitivity had no way to turn the motion off on the first page they see.
 *
 * The CSS guard ships in the layout so every page loading it is covered,
 * including ones whose page stylesheet is added further down the DOM.
 * Durations collapse to 0.01ms rather than none, so an element still
 * reaches its final state instead of being left frozen mid-fade or, worse,
 * permanently invisible.
 */
class ReducedMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_ships_a_reduced_motion_guard(): void
    {
        // The guard belongs in the layout, not in each of the 13 stylesheets:
        // a per-file fix would miss any page that adds its own sheet later.
        $css = $this->layoutStylesheet();

        $this->assertStringContainsString(
            '@media (prefers-reduced-motion: reduce)',
            $css,
            'the layout must ship a reduced-motion guard covering every page'
        );
    }

    public function test_the_guard_reaches_final_state_instead_of_freezing(): void
    {
        $guard = $this->reducedMotionBlock();

        // none would leave a fade-in permanently invisible and an icon spin
        // frozen mid-turn — trading one accessibility defect for another.
        // 0.01ms is effectively instant, but the element still resolves.
        $this->assertStringContainsString(
            'animation-duration: 0.01ms',
            $guard,
            'animations must resolve rather than be removed outright'
        );
        $this->assertStringContainsString(
            'transition-duration: 0.01ms',
            $guard,
            'transitions must resolve rather than be removed outright'
        );
        $this->assertStringContainsString(
            'animation-iteration-count: 1',
            $guard,
            'an infinite loop must stop, not hang on its first frame'
        );
        $this->assertStringContainsString(
            'scroll-behavior: auto',
            $guard,
            'smooth scrolling is motion too'
        );
    }

    public function test_the_guard_is_universal_not_scoped(): void
    {
        // A guard scoped to .particle or .hero would only cover the one
        // component. The universal selector is what makes one rule cover the
        // whole app, including third-party Bootstrap widgets.
        $this->assertStringContainsString(
            '*,',
            $this->reducedMotionBlock(),
            'the guard must cover every element, not one component'
        );
    }

    public function test_the_welcome_script_honours_the_preference_in_js(): void
    {
        // The CSS guard cannot reach effects generated in JS: the particle
        // field, the scroll parallax and the click ripple are all built here.
        $js = file_get_contents(base_path('public/js/welcome.js'));

        $this->assertStringContainsString(
            "matchMedia('(prefers-reduced-motion: reduce)')",
            $js,
            'the welcome script must read the same preference the CSS guard uses'
        );
        $this->assertStringContainsString(
            'if (prefersReducedMotion()) return;',
            $js,
            'the generated motion effects must be skipped when the preference is set'
        );
    }

    private function layoutStylesheet(): string
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        // The layout ships several <style> blocks and the rule may land in
        // any of them, so reassemble them all before asserting.
        preg_match_all('/<style>(.*?)<\/style>/s', $html, $m);
        $this->assertNotEmpty($m[1], 'the layout must ship inline style blocks');

        return implode("\n", $m[1]);
    }

    private function reducedMotionBlock(): string
    {
        preg_match_all(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{(?:[^{}]|\{[^{}]*\})*\}/s',
            $this->layoutStylesheet(),
            $m
        );
        $this->assertNotEmpty($m[0], 'a reduced-motion media block must ship');

        return $m[0][0];
    }
}
