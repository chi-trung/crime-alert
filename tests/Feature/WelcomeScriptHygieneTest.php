<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #359: three defects in public/js/welcome.js, which welcome.blade.php
 * loads on the landing page.
 *
 * 1. The smooth-scroll block ran at script-parse time while the file was
 *    loaded in <head>, so the anchor NodeList was always empty — and the
 *    page has zero href="#" anchors anyway, so nothing was ever going to be
 *    bound. Dead from the start.
 * 2. handleParallax() queried '.background' and '.particles' *inside* its
 *    scroll handler, so both DOM lookups re-ran on every scroll event
 *    instead of resolving once at setup.
 * 3. The script tag sat in <head> without defer, blocking parse for a file
 *    whose real work is all inside DOMContentLoaded.
 *
 * The assertions read the served file rather than a copy, so they follow the
 * asset the browser actually receives.
 */
class WelcomeScriptHygieneTest extends TestCase
{
    use RefreshDatabase;

    private const JS_PATH = 'js/welcome.js';

    /**
     * Slice from a top-level `function name(` to the line that closes it, so
     * an assertion can reason about *where* a call sits, not just whether it
     * exists. A brace-counting scanner is the right tool: welcome.js carries
     * template literals holding unbalanced braces (the ripple cssText), which
     * defeats a naive regex. Only the outermost level is ever needed here, so
     * strings and comments are left uninterpreted — none of them contains a
     * brace at depth zero.
     */
    private function functionBody(string $script, string $name): string
    {
        $found = preg_match(
            '/^function\s+'.preg_quote($name, '/').'\s*\(/m',
            $script,
            $m,
            PREG_OFFSET_CAPTURE
        );

        $this->assertSame(1, $found, "function {$name}() must be defined once in welcome.js");

        $i = $m[0][1];
        $start = strpos($script, '{', $i);
        $this->assertNotFalse($start, "function {$name}() must open a body");

        $depth = 0;
        $end = $start;
        for ($len = strlen($script); $end < $len; $end++) {
            $c = $script[$end];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
        }

        $this->assertSame(0, $depth, "function {$name}() must close its body");

        return substr($script, $start + 1, $end - $start - 1);
    }

    /**
     * Resolve the served welcome.js through the page's own tag, so the
     * assertions read the asset the browser actually receives.
     */
    private function welcomeScript(): string
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Resolve the tag the page actually emits, then read the served file.
        preg_match('#src="([^"]*js/welcome\.js)"#', $html, $m);
        $this->assertNotEmpty($m[1], 'welcome.js must be referenced by the landing page');

        $relative = ltrim(str_replace(asset('/'), '', $m[1]), '/');
        $script = file_get_contents(public_path($relative));
        $this->assertNotEmpty($script, 'welcome.js must exist and be non-empty');

        return $script;
    }

    public function test_dead_smooth_scroll_block_is_gone(): void
    {
        $script = $this->welcomeScript();

        // Assert the block's working parts, not a bare token: the #359
        // comment above the removal legitimately names scrollIntoView while
        // explaining what died.
        $this->assertStringNotContainsString(
            "document.querySelectorAll('a[href^=\"#\"]')",
            $script,
            'the dead smooth-scroll lookup must be gone'
        );
        $this->assertStringNotContainsString(
            'scrollIntoView(',
            $script,
            'the dead smooth-scroll target search must be gone'
        );
    }

    public function test_parallax_lookups_resolve_outside_the_scroll_handler(): void
    {
        $script = $this->welcomeScript();

        $head = $this->functionBody($script, 'handleParallax');

        $marker = "window.addEventListener('scroll'";
        $this->assertStringContainsString($marker, $head, 'handleParallax() must register the scroll listener');

        // The shape that matters is *position*, not count: both lookups
        // occur exactly once on either tree, but only on the fixed one do
        // they sit before the listener marker. Inside the handler they
        // re-query the DOM on every scroll event.
        $at = strpos($head, $marker);
        $before = substr($head, 0, $at);

        $this->assertStringContainsString(
            "document.querySelector('.background')",
            $before,
            'the background lookup must resolve at setup, before the handler runs'
        );
        $this->assertStringContainsString(
            "document.querySelector('.particles')",
            $before,
            'the particles lookup must resolve at setup, before the handler runs'
        );
    }

    public function test_dom_content_loaded_init_survives(): void
    {
        // Positive control: the removal must not cost the page its
        // particles, observer, parallax and ripple wiring.
        $script = $this->welcomeScript();

        $this->assertStringContainsString('createParticles();', $script);
        $this->assertStringContainsString('setupIntersectionObserver();', $script);
        $this->assertStringContainsString('handleParallax();', $script);
        $this->assertStringContainsString("addEventListener('DOMContentLoaded'", $script);
    }

    public function test_landing_page_defers_the_script(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'js/welcome.js" defer',
            $html,
            'welcome.js must be deferred so it no longer blocks page parse'
        );
    }

    public function test_landing_page_has_no_hash_anchors(): void
    {
        // Pins why the removed block was dead: the page has no anchor the
        // smooth-scroll handler could ever have matched.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="#', $html, 'the landing page has no hash anchor');
    }
}
