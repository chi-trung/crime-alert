<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #295 (VIEW-1): the #77 sweep converted alerts_map.js popups from
 * template literals into textContent nodes, but the alerts_create.js map
 * catch kept the dangerous shape: mapEl.innerHTML = `...${error.message}`.
 * Leaflet throws "Invalid LatLng object: (<raw>, <raw>)" embedding the very
 * bytes it was handed, and the hidden inputs are repopulated verbatim by
 * old('latitude') (resources/views/alerts/create.blade.php L79-80) after a
 * failed submit — so an authenticated user who submits latitude =
 * `<img src=x onerror=...>` gets their own markup executed back at them in
 * the catch: self-XSS, exactly the #77/#18 class, one form away. The fix
 * gates marker/pan/reverseGeocode on Number.isFinite (so no throw happens
 * with garbage at all) and builds the fallback alert from a text node.
 *
 * Issue #349: the map init moved from alerts_create.js to the shared
 * alert_map_picker.js (the edit page needs the identical map), so the guards
 * are pinned wherever the code lives. The two files are asserted together:
 * a copy of the dangerous shape in either one reaches a live page.
 */
class AlertsCreateErrorSinkTest extends TestCase
{
    use RefreshDatabase;

    private string $js;

    protected function setUp(): void
    {
        parent::setUp();
        $this->js = file_get_contents(public_path('js/alert_map_picker.js'))
            ."\n".file_get_contents(public_path('js/alerts_create.js'));
    }

    public function test_no_interpolated_template_literal_reaches_innerhtml(): void
    {
        // The dangerous shape is specifically `X.innerHTML = \`...${...}\`` —
        // static string innerHTML (the fixed locate-button markup) stays OK.
        $this->assertDoesNotMatchRegularExpression(
            '/\.innerHTML\s*=\s*`/',
            $this->js,
            'a template literal is being assigned to innerHTML again'
        );
        $this->assertStringNotContainsString('${error.message}', $this->js);
    }

    public function test_map_error_alert_is_built_with_textcontent_nodes(): void
    {
        // Positive controls so the negative pins above can't be satisfied by
        // deleting the error branch entirely.
        $this->assertMatchesRegularExpression(
            '/\.textContent\s*=\s*./',
            $this->js,
            'the error alert must be assembled from a text node'
        );
        $this->assertStringContainsString('alert alert-danger p-3', $this->js, 'the styled fallback must survive');
        $this->assertStringContainsString("console.error('Lỗi khi tạo bản đồ:', error)", $this->js);
    }

    public function test_marker_path_is_gated_on_finite_coordinates(): void
    {
        // Number('') === 0 is finite, so the empty check must stand too;
        // pinning the guard keeps invalid old() values from reaching L.marker
        // at all (that throw was what smuggled raw bytes into error.message).
        $this->assertStringContainsString('Number.isFinite', $this->js);
    }

    public function test_reverse_geocode_query_params_are_encoded(): void
    {
        // Issue #349: the picker builds the URL from string concatenation
        // rather than a template literal (the surrounding code is ES5 so the
        // page stays parseable by older engines), so the literal pin is
        // asserted against the encoded pair directly.
        $this->assertStringContainsString(
            'reverse?lat='."' + encodeURIComponent(lat)",
            $this->js,
            'untrusted input must be percent-encoded into the outbound URL'
        );
        $this->assertStringContainsString(
            "' + encodeURIComponent(lng)",
            $this->js
        );
        $this->assertDoesNotMatchRegularExpression(
            '/reverse\?lat=\$\{[^}]*\}/',
            $this->js,
            'the coordinates must not be interpolated raw into the URL'
        );
    }

    public function test_a_rejected_submit_returns_raw_latitude_to_the_page_old_input(): void
    {
        // End-to-end evidence that the static pins above close a live data
        // path, not a theoretical one (control — passes before and after the
        // fix): 'latitude' => 'nullable|numeric' on AlertController::store
        // rejects the markup, and the failed validation's withInput() flash
        // puts the raw bytes in session old-input; create.blade.php L79 then
        // renders old('latitude') into the hidden input ({{ }} escapes the
        // attribute, so nothing executes IN the markup) that initializeMap
        // reads back. The DOM parser decodes the attribute to RAW text at
        // .value, and the ONLY thing that turned that read into script
        // execution was the innerHTML sink pinned closed above.
        $user = User::factory()->create();
        $payload = '<img src=x onerror=alert(1)>';

        $this->actingAs($user)
            ->post('/alerts', [
                'title' => 'Hop le',
                'type' => 'Trộm cắp',
                'description' => 'd',
                'latitude' => $payload,
                'longitude' => $payload,
            ])
            ->assertSessionHasErrors('latitude')
            ->assertSessionHasInput('latitude', $payload);
    }
}
