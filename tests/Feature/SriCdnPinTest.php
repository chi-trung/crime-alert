<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #331: #327 pinned + SRI'd eight CDN assets across seven views
 * (leaflet js+css, leaflet.markercluster js + both CSS, tailwindcss play
 * CDN, font-awesome 6.4.0/6.4.2, bootstrap CSS + bundle JS). #167's and
 * #221's tests only pin their own assets (leaflet-control-geocoder,
 * chart.js, sweetalert2) — GeocoderCdnPinTest:14 even DESCRIBED the
 * leaflet/markercluster siblings as "the pinned" pair while they were
 * pinned-but-integrity-less, which is exactly the blind spot that let the
 * gap live until #327. Nothing asserted these eight tags after the fix, so
 * dropping an integrity attribute or a wrong hash regressed silently — and
 * the repo ships no CSP, making SRI the only CDN integrity layer.
 *
 * Doctrine mirrors FloatingCdnPinTest: the rendered HTML must carry the
 * version pin, the integrity hash, and crossorigin="anonymous" (inert
 * without it cross-origin), the bare/unpinned form must be gone, and the
 * hash constants are re-checked against upstream (marked skipped when the
 * runner is offline, same idiom as #167/#221).
 */
class SriCdnPinTest extends TestCase
{
    use RefreshDatabase;

    private const LEAFLET_JS_HASH = 'sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH';

    private const LEAFLET_CSS_HASH = 'sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H';

    private const MARKERCLUSTER_JS_HASH = 'sha384-eXVCORTRlv4FUUgS/xmOyr66XBVraen8ATNLMESp92FKXLAMiKkerixTiBvXriZr';

    private const MARKERCLUSTER_CSS_HASH = 'sha384-pmjIAcz2bAn0xukfxADbZIb3t8oRT9Sv0rvO+BR5Csr6Dhqq+nZs59P0pPKQJkEV';

    private const MARKERCLUSTER_DEFAULT_CSS_HASH = 'sha384-wgw+aLYNQ7dlhK47ZPK7FRACiq7ROZwgFNg0m04avm4CaXS+Z9Y7nMu8yNjBKYC+';

    private const TAILWIND_HASH = 'sha384-igm5BeiBt36UU4gqwWS7imYmelpTsZlQ45FZf+XBn9MuJbn4nQr7yx1yFydocC/K';

    private const FONT_AWESOME_640_HASH = 'sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0';

    private const FONT_AWESOME_642_HASH = 'sha384-blOohCVdhjmtROpu8+CfTnUWham9nkX7P7OZQMst+RUnhtoY/9qemFAkIKOYxDI3';

    private const BOOTSTRAP_CSS_HASH = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';

    private const BOOTSTRAP_JS_HASH = 'sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz';

    /**
     * The map view carries the heaviest CDN load and is public, so it is the
     * cheapest full probe of the leaflet + markercluster family.
     */
    public function test_alerts_map_ships_pinned_sri_leaflet_and_markercluster_tags(): void
    {
        // /alerts/map sits behind auth (verified by AlertsMapPayloadTest's
        // own 302-then-login shape), unlike a truly public landing page.
        $html = $this->actingAs(User::factory()->create())
            ->get('/alerts/map')
            ->assertOk()
            ->getContent();

        // Core leaflet pair (also on create + edit).
        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.js', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_JS_HASH.'"', $html);
        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.css', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_CSS_HASH.'"', $html);

        // The markercluster triple lives only on the map.
        $this->assertStringContainsString('unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', $html);
        $this->assertStringContainsString('integrity="'.self::MARKERCLUSTER_JS_HASH.'"', $html);
        $this->assertStringContainsString('unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css', $html);
        $this->assertStringContainsString('integrity="'.self::MARKERCLUSTER_CSS_HASH.'"', $html);
        $this->assertStringContainsString('unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css', $html);
        $this->assertStringContainsString('integrity="'.self::MARKERCLUSTER_DEFAULT_CSS_HASH.'"', $html);

        // integrity without crossorigin is inert for cross-origin requests.
        foreach ([
            self::LEAFLET_JS_HASH,
            self::LEAFLET_CSS_HASH,
            self::MARKERCLUSTER_JS_HASH,
            self::MARKERCLUSTER_CSS_HASH,
            self::MARKERCLUSTER_DEFAULT_CSS_HASH,
        ] as $hash) {
            $this->assertStringContainsString($hash.'" crossorigin="anonymous"', $html);
        }

        // The bare form must be gone: unpkg resolves it to upstream latest.
        $this->assertStringNotContainsString('unpkg.com/leaflet/dist/', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet.markercluster/dist/', $html);
    }

    public function test_alerts_create_ships_pinned_sri_leaflet_pair(): void
    {
        // The form needs a verified user (AlertController::store's gate is on
        // POST; create() only renders, but the route sits behind auth).
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get('/alerts/create')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.js', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_JS_HASH.'"', $html);
        $this->assertStringContainsString(self::LEAFLET_JS_HASH.'" crossorigin="anonymous"', $html);
        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.css', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_CSS_HASH.'"', $html);
        $this->assertStringContainsString(self::LEAFLET_CSS_HASH.'" crossorigin="anonymous"', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet/dist/', $html);
    }

    public function test_alerts_edit_ships_pinned_sri_leaflet_pair(): void
    {
        // edit() aborts 403 unless the viewer is admin or the owner. The
        // project ships no Alert factory, so build the row directly (same
        // shape as AlertTest / AlertImageSrcTest).
        $owner = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $owner->id,
            'title' => 'SRI pin probe',
            'description' => 'd',
            'type' => 'Trộm cắp',
            'status' => 'pending',
        ]);

        $html = $this->actingAs($owner)
            ->get("/alerts/{$alert->id}/edit")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.js', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_JS_HASH.'"', $html);
        $this->assertStringContainsString(self::LEAFLET_JS_HASH.'" crossorigin="anonymous"', $html);
        $this->assertStringContainsString('unpkg.com/leaflet@1.9.4/dist/leaflet.css', $html);
        $this->assertStringContainsString('integrity="'.self::LEAFLET_CSS_HASH.'"', $html);
        $this->assertStringContainsString(self::LEAFLET_CSS_HASH.'" crossorigin="anonymous"', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet/dist/', $html);
    }

    public function test_login_ships_pinned_sri_tailwind_and_font_awesome(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // #327's worst find: cdn.tailwindcss.com bare served upstream latest,
        // the JIT compiler scanning every class in both pages' DOM.
        $this->assertStringContainsString('cdn.tailwindcss.com/3.4.17', $html);
        $this->assertStringContainsString('integrity="'.self::TAILWIND_HASH.'"', $html);
        $this->assertStringContainsString(self::TAILWIND_HASH.'" crossorigin="anonymous"', $html);

        $this->assertStringContainsString('font-awesome/6.4.0/css/all.min.css', $html);
        $this->assertStringContainsString('integrity="'.self::FONT_AWESOME_640_HASH.'"', $html);
        $this->assertStringContainsString(self::FONT_AWESOME_640_HASH.'" crossorigin="anonymous"', $html);

        // The bare play CDN URL must not survive anywhere on the page.
        $this->assertStringNotContainsString('src="https://cdn.tailwindcss.com"', $html);
    }

    public function test_register_ships_pinned_sri_tailwind_and_font_awesome(): void
    {
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('cdn.tailwindcss.com/3.4.17', $html);
        $this->assertStringContainsString('integrity="'.self::TAILWIND_HASH.'"', $html);
        $this->assertStringContainsString(self::TAILWIND_HASH.'" crossorigin="anonymous"', $html);

        $this->assertStringContainsString('font-awesome/6.4.0/css/all.min.css', $html);
        $this->assertStringContainsString('integrity="'.self::FONT_AWESOME_640_HASH.'"', $html);
        $this->assertStringContainsString(self::FONT_AWESOME_640_HASH.'" crossorigin="anonymous"', $html);

        $this->assertStringNotContainsString('src="https://cdn.tailwindcss.com"', $html);
    }

    /**
     * The layout reaches every authed page via extends('layouts.app'); /dashboard
     * (auth+verified) is the canonical consumer, same choice as #221's test.
     */
    public function test_layout_ships_pinned_sri_bootstrap_and_font_awesome(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        // Bootstrap pair: the bundle sits outside the @if(admin) block, so it
        // executes on every authed page.
        $this->assertStringContainsString('cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', $html);
        $this->assertStringContainsString('integrity="'.self::BOOTSTRAP_CSS_HASH.'"', $html);
        $this->assertStringContainsString(self::BOOTSTRAP_CSS_HASH.'" crossorigin="anonymous"', $html);
        $this->assertStringContainsString('cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', $html);
        $this->assertStringContainsString('integrity="'.self::BOOTSTRAP_JS_HASH.'"', $html);
        $this->assertStringContainsString(self::BOOTSTRAP_JS_HASH.'" crossorigin="anonymous"', $html);

        // Font-awesome 6.4.2 (the layout's copy; login/register carry 6.4.0).
        $this->assertStringContainsString('font-awesome/6.4.2/css/all.min.css', $html);
        $this->assertStringContainsString('integrity="'.self::FONT_AWESOME_642_HASH.'"', $html);
        $this->assertStringContainsString(self::FONT_AWESOME_642_HASH.'" crossorigin="anonymous"', $html);

        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/bootstrap@5.3.3"', $html);
    }

    public function test_pinned_hashes_match_the_upstream_assets(): void
    {
        // Tripwire against a wrong transcription (same idiom as #167's and
        // #221's tests; the @version dist files themselves are immutable).
        $expected = [
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js' => self::LEAFLET_JS_HASH,
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css' => self::LEAFLET_CSS_HASH,
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js' => self::MARKERCLUSTER_JS_HASH,
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css' => self::MARKERCLUSTER_CSS_HASH,
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css' => self::MARKERCLUSTER_DEFAULT_CSS_HASH,
            'https://cdn.tailwindcss.com/3.4.17' => self::TAILWIND_HASH,
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' => self::FONT_AWESOME_640_HASH,
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css' => self::FONT_AWESOME_642_HASH,
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' => self::BOOTSTRAP_CSS_HASH,
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' => self::BOOTSTRAP_JS_HASH,
        ];

        $context = stream_context_create(['http' => ['timeout' => 15]]);

        foreach ($expected as $url => $hash) {
            $asset = @file_get_contents($url, false, $context);
            if ($asset === false) {
                $this->markTestSkipped("CDN unreachable from this runner: {$url}");
            }
            $this->assertSame($hash, 'sha384-'.base64_encode(hash('sha384', $asset, true)), $url);
        }
    }
}
