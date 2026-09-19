<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #412: the leaflet "locate me" control is built by two scripts
 * (alerts_map.js for /alerts/map, alert_map_picker.js for create/edit) and
 * both built it from a byte-identical innerHTML string that supplied only
 * title= — a tooltip, not an accessible name, the same defect #408 closed on
 * the comment like button. The decorative glyph was not hidden either.
 *
 * On top of the name, the failure paths were raw alert(): a blocking,
 * styleless, inaccessible dialog, and the button looked dead when geolocation
 * was denied. They now announce into a polite live region.
 *
 * These are contract tests over the script sources rather than runtime probes:
 * the control is built by a Leaflet L.control() onAdd callback that only fires
 * once the map initialises, which does not happen in a request/response test.
 */
class LocateButtonA11yTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $scripts = [
        'public/js/alerts_map.js',
        'public/js/alert_map_picker.js',
    ];

    public function test_the_locate_button_is_named_in_both_scripts(): void
    {
        foreach ($this->scripts as $path) {
            $js = $this->script($path);

            $this->assertStringContainsString(
                'aria-label="Lấy vị trí của tôi"',
                $js,
                $path.' must name the locate button — title= is a tooltip, not an accessible name'
            );
            $this->assertStringNotContainsString(
                'id="locateMeBtn" title=',
                $js,
                $path.' must not reach for the button through title alone'
            );
        }
    }

    public function test_the_locate_glyph_is_decorative_in_both_scripts(): void
    {
        foreach ($this->scripts as $path) {
            $this->assertStringContainsString(
                'class="fas fa-location-arrow" aria-hidden="true"',
                $this->script($path),
                $path.' must hide the arrow glyph from the accessibility tree'
            );
        }
    }

    public function test_the_locate_button_ids_stay_unique_per_page(): void
    {
        // The two scripts are byte-identical for the button string, but they
        // load on different pages (map vs create/edit), so the id does not
        // collide in practice. Pin that separation so a future shared <script>
        // include does not silently double-register the id.
        $consumers = [
            'public/js/alerts_map.js' => 'resources/views/alerts/map.blade.php',
            'public/js/alert_map_picker.js' => 'resources/views/alerts/create.blade.php',
        ];

        foreach ($consumers as $script => $page) {
            $this->assertStringContainsString(
                basename($script),
                file_get_contents(base_path($page)),
                $page.' must load '.$script.' — the two locate buttons stay unique by never sharing a page'
            );
        }
    }

    public function test_location_failures_are_announced_not_alerted(): void
    {
        foreach ($this->scripts as $path) {
            $js = $this->script($path);

            // A raw alert() is a blocking modal with no semantics; the failure
            // paths are the ones users hit most (permission denied, no GPS).
            $this->assertStringNotContainsString(
                "alert('Không thể lấy vị trí của bạn!')",
                $js,
                $path.' must not surface geolocation failure through a raw alert()'
            );
            $this->assertStringNotContainsString(
                "alert('Trình duyệt không hỗ trợ định vị!')",
                $js,
                $path.' must not surface the unsupported-browser path through a raw alert()'
            );

            $this->assertStringContainsString(
                'aria-live',
                $js,
                $path.' must announce the geolocation outcome into a live region'
            );
            $this->assertStringContainsString(
                'announceLocation(',
                $js,
                $path.' must route every outcome through the shared announcer'
            );
        }
    }

    public function test_the_announcer_hides_itself_without_a_stylesheet(): void
    {
        // /alerts/map ships no page stylesheet, so the region cannot rely on a
        // .sr-only class. The clip is applied inline; display:none would
        // silence the region and undo the fix.
        foreach ($this->scripts as $path) {
            $js = $this->script($path);

            $this->assertStringContainsString(
                'clip:rect(0,0,0,0)',
                $js,
                $path.' must clip the announcer inline because the map pages ship no .sr-only rule'
            );
            $this->assertStringNotContainsString(
                "className = 'sr-only'",
                $js,
                $path.' cannot rely on a .sr-only class the map pages do not load'
            );
        }
    }

    private function script(string $path): string
    {
        return file_get_contents(base_path($path));
    }
}
