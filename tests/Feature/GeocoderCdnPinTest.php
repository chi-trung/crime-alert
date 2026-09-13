<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #167: alerts/map and alerts/create loaded the geocoder plugin as
 * https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.{css,js}
 * — no version, no integrity — so every authed page executed whatever
 * upstream 'latest' pointed at the time (already silently moved 3.x ->
 * 4.0.0), unlike the pinned leaflet 1.9.4 / markercluster 1.5.3 siblings on
 * the same lines. Both views now carry the 4.0.0 pin plus sha384 SRI
 * (crossorigin makes the integrity check actually run in CORS mode); these
 * tests pin the rendered HTML — a bare geocoder URL that creeps back would
 * fail them, and so would an integrity value that no longer matches the
 * pinned asset (the hash constants below are the values verified against
 * the 4.0.0 files; the egress test re-checks they still match upstream).
 */
class GeocoderCdnPinTest extends TestCase
{
    use RefreshDatabase;

    private const CSS_HASH = 'sha384-dtZhMVplthx1XPTPFEKMM5M6e369Paz7gy0QTqvuQKB42lq4FIPsrqe125Ho6bfO';

    private const JS_HASH = 'sha384-GwOxBPYQUJoAtZlP9zcDGxDFHdgRasiwmwj4JQoxhWpOBaETX1aOU/qm8fsP4Hf5';

    private function assertPinned(string $html): void
    {
        // The two geocoder assets, version-pinned with matching SRI.
        $this->assertStringContainsString('unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.css', $html);
        $this->assertStringContainsString('unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.js', $html);
        $this->assertStringContainsString('integrity="'.self::CSS_HASH.'"', $html);
        $this->assertStringContainsString('integrity="'.self::JS_HASH.'"', $html);
        // integrity without crossorigin is inert for cross-origin requests —
        // the browser refuses to compare hashes on an opaque response.
        $this->assertStringContainsString('crossorigin="anonymous"', $html);

        // Pre-fix shape must be gone from both views.
        $this->assertStringNotContainsString('unpkg.com/leaflet-control-geocoder/dist/', $html);
    }

    public function test_map_page_ships_pinned_sri_geocoder_tags(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/alerts/map')
            ->assertOk()
            ->assertSee('leaflet-control-geocoder@4.0.0', false);

        $this->assertPinned($this->get('/alerts/map')->getContent());
    }

    public function test_create_page_ships_pinned_sri_geocoder_tags(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/alerts/create')
            ->assertOk();

        $this->assertPinned($this->get('/alerts/create')->getContent());
    }

    public function test_pinned_hashes_match_the_upstream_assets(): void
    {
        // The SRI constants above are only true as long as the @4.0.0 dist
        // files are unchanged (they are versioned, so this is a tripwire
        // against a wrong transcription, not against upstream drift).
        $css = @file_get_contents('https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.css', false, stream_context_create(['http' => ['timeout' => 15]]));
        $js = @file_get_contents('https://unpkg.com/leaflet-control-geocoder@4.0.0/dist/Control.Geocoder.js', false, stream_context_create(['http' => ['timeout' => 15]]));
        if ($css === false || $js === false) {
            $this->markTestSkipped('unpkg unreachable from this runner.');
        }
        $this->assertSame(self::CSS_HASH, 'sha384-'.base64_encode(hash('sha384', $css, true)));
        $this->assertSame(self::JS_HASH, 'sha384-'.base64_encode(hash('sha384', $js, true)));
    }
}
