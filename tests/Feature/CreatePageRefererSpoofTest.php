<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #245: alerts/create.blade.php rendered url()->previous() straight
 * into the "Quay lại" anchor. UrlGenerator::previous() PREFERS the raw
 * Referer header (over the session snapshot) and UrlGenerator::to() returns
 * any isValidUrl() string — including absolute https://host and
 * protocol-relative //host forms — verbatim. A link planted on an attacker
 * site pointing at /alerts/create (an auth-only page; a top-level navigation
 * still carries the SameSite=lax session cookie) therefore rendered a
 * genuine, logged-in crime-alert page whose prominent back button was a real
 * anchor to the attacker's URL — link-spoofing on a trusted surface, and
 * unlike a 302 it survives every later navigation. #110 gated exactly this
 * trust for notification redirect targets; the rendered-href face was never
 * covered. The fix routes the value through LocalUrl (the extracted #110
 * rule) and falls back to the alerts list when the previous URL is not ours.
 */
class CreatePageRefererSpoofTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_referer_is_not_rendered_into_the_back_link(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->withHeaders(['Referer' => 'https://evil.example/phishing'])
            ->get(route('alerts.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('href="'.route('alerts.index').'"', $html,
            'the spoofed previous must fall back to the alerts list');
    }

    public function test_protocol_relative_referer_is_not_rendered_into_the_back_link(): void
    {
        // //evil.example passes UrlGenerator::to()'s isValidUrl() untouched
        // (the browser would later resolve it against whatever scheme the
        // page is served on) and must fail the host comparison, not inherit
        // the request scheme.
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->withHeaders(['Referer' => '//evil.example/x'])
            ->get(route('alerts.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('href="'.route('alerts.index').'"', $html);
    }

    public function test_javascript_pseudo_url_referer_cannot_reach_the_href(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->withHeaders(['Referer' => 'javascript:alert(document.cookie)'])
            ->get(route('alerts.create'))
            ->assertOk()
            ->getContent();

        // The page's own navigation legitimately contains
        // href="javascript:void(0)" (#215), so pin the payload instead:
        // neither the verbatim header nor UrlGenerator::to()'s
        // app-URL-prefixed laundering of it may appear in any href, and the
        // fallback must render.
        $this->assertStringNotContainsString('alert(document.cookie)', $html);
        $this->assertStringNotContainsString('href="http://localhost/javascript:', $html);
        $this->assertStringNotContainsString('href="javascript:alert', $html);
        $this->assertStringContainsString('href="'.route('alerts.index').'"', $html);
    }

    public function test_a_genuine_internal_previous_url_is_still_respected(): void
    {
        // The gate must not degrade honest navigation: walking
        // /alerts -> /alerts/create (no Referer header, so the session
        // snapshot is the source) still renders the list URL as the back
        // target — the real UX the button exists for.
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('alerts.index'))->assertOk();
        $html = $this->actingAs($user)->get(route('alerts.create'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('alerts.index').'"', $html);
    }

    public function test_localurl_rule_matches_the_110_semantics(): void
    {
        // The shared rule now serves BOTH faces (notification 302s and
        // rendered hrefs); pin its table so neither call site can loosen it
        // unnoticed. config('app.url') here is http://localhost.
        $this->assertTrue(LocalUrl::isLocal('/relative/path'));
        $this->assertTrue(LocalUrl::isLocal('/'));
        $this->assertTrue(LocalUrl::isLocal(config('app.url').'/alerts/create'));
        $this->assertFalse(LocalUrl::isLocal('//evil.example/x'));
        $this->assertFalse(LocalUrl::isLocal('https://evil.example/phish'));
        $this->assertFalse(LocalUrl::isLocal('http://EVIL.example/x'), 'host compare is case-insensitive, both ways');
        $this->assertFalse(LocalUrl::isLocal('javascript:alert(1)'));
        $this->assertFalse(LocalUrl::isLocal('mailto:someone@example.com'));
        $this->assertFalse(LocalUrl::isLocal(''));
    }
}
