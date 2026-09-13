<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Issue #77: the map popups were an HTML template literal interpolating
 * alert.title/type/location — Leaflet innerHTMLs a bindPopup(string), so any
 * authenticated viewer of /alerts/map could be hit by a stored script placed
 * in a title. The fix builds DOM nodes with textContent (issue #18's
 * notification-dropdown idiom). The popup HTML never reaches a test's
 * response — the file is a static asset — so this pins the source itself.
 */
class AlertsMapPopupTest extends TestCase
{
    private string $js;

    protected function setUp(): void
    {
        parent::setUp();
        $this->js = file_get_contents(public_path('js/alerts_map.js'));
    }

    public function test_popup_does_not_interpolate_user_fields_into_html(): void
    {
        foreach (['alert.title', 'alert.type', 'alert.location'] as $field) {
            $this->assertStringNotContainsString(
                '${'.$field.'}',
                $this->js,
                "user field {$field} is interpolated into an HTML template literal again"
            );
        }
    }

    public function test_popup_is_built_with_textcontent_nodes(): void
    {
        // Positive controls so the negative assertions above can't be
        // satisfied by deleting the popup entirely.
        $this->assertStringContainsString('titleEl.textContent = alert.title', $this->js);
        $this->assertStringContainsString('badge.textContent = alert.type', $this->js);
        $this->assertStringContainsString('locEl.textContent = alert.location', $this->js);
        $this->assertStringContainsString('marker.bindPopup(popup)', $this->js);
    }

    public function test_detail_link_path_component_is_encoded(): void
    {
        // id is a bigint so this is defense in depth, not a live hole —
        // but an href must still be assembled, not interpolated.
        $this->assertStringContainsString("'/alerts/' + encodeURIComponent(alert.id)", $this->js);
    }

    public function test_no_html_string_reaches_bindpopup(): void
    {
        // The only bindPopup call that carries data must receive an element.
        // ('Vị trí của bạn' is a static literal with no user input.)
        $this->assertMatchesRegularExpression('/bindPopup\(popup\)/', $this->js);
        $this->assertDoesNotMatchRegularExpression('/bindPopup\(`/', $this->js);
    }
}
