<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #221: two script tags still loaded CDN code with no version pin or
 * no integrity — dashboard.blade.php's bare https://cdn.jsdelivr.net/npm/
 * chart.js (jsdelivr resolves it to upstream "latest" on every render, so
 * every auth+verified /dashboard view silently executed third-party code
 * churn, with the tag even outside the @if(admin) block) and the layout's
 * sweetalert2@11 (major-pinned only; every upstream v11.x minor moved the
 * whole app's dialogs). #167 fixed exactly this class for the geocoder
 * assets and its body named these two as the remaining siblings; the fix
 * pins both to the exact builds verified byte-identical to what the floating
 * URLs served at fix time, and these tests mirror GeocoderCdnPinTest: the
 * rendered HTML must carry the pins, the bare forms must be gone, and the
 * SRI constants are re-checked against upstream (marked skipped offline).
 */
class FloatingCdnPinTest extends TestCase
{
    use RefreshDatabase;

    private const CHART_HASH = 'sha384-jb8JQMbMoBUzgWatfe6COACi2ljcDdZQ2OxczGA3bGNeWe+6DChMTBJemed7ZnvJ';

    private const SWAL_HASH = 'sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2';

    public function test_dashboard_ships_pinned_sri_chart_tag(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js', $html);
        $this->assertStringContainsString('integrity="'.self::CHART_HASH.'"', $html);
        // integrity without crossorigin is inert for cross-origin requests.
        $this->assertStringContainsString('crossorigin="anonymous"', $html);
        // The floating form must be gone: tag-hay-not-có-path luôn là latest.
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/chart.js"', $html);
    }

    public function test_layout_ships_pinned_sri_sweetalert_tag(): void
    {
        // The layout's tag reaches every page extending layouts.app — the
        // landing page is a standalone document without it, so /dashboard
        // (auth+verified) is the real consumer, and every authed page
        // inherits the same <script>.
        $html = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js', $html);
        $this->assertStringContainsString('integrity="'.self::SWAL_HASH.'"', $html);
        $this->assertStringContainsString('crossorigin="anonymous"', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net/npm/sweetalert2@11"', $html);
    }

    public function test_pinned_hashes_match_the_upstream_assets(): void
    {
        // Tripwire against a wrong transcription (same idiom as #167's test;
        // the @version dist files themselves are immutable).
        $chart = @file_get_contents('https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js', false, stream_context_create(['http' => ['timeout' => 15]]));
        $swal = @file_get_contents('https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js', false, stream_context_create(['http' => ['timeout' => 15]]));
        if ($chart === false || $swal === false) {
            $this->markTestSkipped('jsdelivr unreachable from this runner.');
        }
        $this->assertSame(self::CHART_HASH, 'sha384-'.base64_encode(hash('sha384', $chart, true)));
        $this->assertSame(self::SWAL_HASH, 'sha384-'.base64_encode(hash('sha384', $swal, true)));
    }
}
