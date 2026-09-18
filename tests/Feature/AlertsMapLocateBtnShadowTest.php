<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #361: public/js/alerts_map.js declared `locateBtn` twice inside one
 * function scope — L27 the Leaflet `L.control` object, L98 the DOM button it
 * injects. The control reference was shadowed from the second line on, and
 * the handler was attached synchronously in DOMContentLoaded even though
 * `L.control::addTo()` inserts its DOM node asynchronously. The sibling
 * picker (alert_map_picker.js) already does both correctly; this aligns the
 * shared control on the public map page with it.
 *
 * The assertions read the served asset rather than a copy, so they follow
 * the file the browser actually receives.
 */
class AlertsMapLocateBtnShadowTest extends TestCase
{
    use RefreshDatabase;

    private function mapScript(): string
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        $html = $this->actingAs($user)->get(route('alerts.map'))->assertOk()->getContent();

        preg_match('#src="([^"]*js/alerts_map\.js)"#', $html, $m);
        $this->assertNotEmpty($m[1], 'alerts_map.js must be referenced by the map page');

        $relative = ltrim(str_replace(asset('/'), '', $m[1]), '/');
        $script = file_get_contents(public_path($relative));
        $this->assertNotEmpty($script, 'alerts_map.js must exist and be non-empty');

        return $script;
    }

    public function test_the_control_name_is_declared_once(): void
    {
        $script = $this->mapScript();

        // Three occurrences is the correct shape: the declaration, the
        // onAdd assignment, the addTo() call. Four means the DOM button
        // reused the name again.
        $this->assertSame(
            3,
            substr_count($script, 'locateBtn'),
            'locateBtn must name only the L.control, not also the DOM button'
        );
    }

    public function test_the_dom_button_uses_its_own_name(): void
    {
        $script = $this->mapScript();

        $this->assertStringContainsString(
            "var locateButton = document.getElementById('locateMeBtn')",
            $script,
            'the DOM button must not shadow the control reference'
        );
    }

    public function test_the_handler_is_attached_on_the_next_tick(): void
    {
        // The control inserts its button asynchronously, so the handler is
        // attached on the next tick — the same shape the sibling picker uses.
        $script = $this->mapScript();
        $pos = strpos($script, "var locateButton = document.getElementById('locateMeBtn')");

        $this->assertNotFalse($pos, 'the renamed DOM lookup must be present');

        $before = substr($script, 0, $pos);

        $this->assertStringEndsWith(
            'setTimeout(function () {',
            rtrim($before),
            'the handler must be attached inside a setTimeout, not synchronously'
        );
    }

    public function test_the_control_and_marker_wiring_survive(): void
    {
        // Positive control: the rename must not cost the map its locate
        // control, its marker function or the geocoder.
        $script = $this->mapScript();

        $this->assertStringContainsString("var locateBtn = L.control({position: 'topleft'});", $script);
        $this->assertStringContainsString('locateBtn.addTo(map);', $script);
        $this->assertStringContainsString("getElementById('locateMeBtn')", $script);
        $this->assertStringContainsString('addCurrentLocationMarker(lat, lng);', $script);
        $this->assertStringContainsString('L.Control.geocoder({', $script);
    }
}
