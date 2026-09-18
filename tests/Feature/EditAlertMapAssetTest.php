<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #349: the edit-alert page shipped a stripped-down copy of the create
 * page's map, and the two gaps were both user-visible:
 *
 *  - the inline block never called the Leaflet default-icon fix, so its
 *    markers did not render at all (Leaflet 1.9 emits no <img> without it);
 *  - it had no geocoder and no location field, so an edit could move the
 *    point and leave a stale address next to the new coordinates.
 *
 * The map is now the shared alert_map_picker.js. These pin both the fix and
 * the four dead asset files removed in the same round.
 */
class EditAlertMapAssetTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        // /alerts/{id}/edit requires a verified email (see AlertController::update,
        // issue #237) and the edit form is only reached by the owner or an admin.
        return tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());
    }

    private function alertFor(User $owner): Alert
    {
        return Alert::create([
            'user_id' => $owner->id,
            'title' => 'Cướp giật tại ngã tư',
            'description' => 'Mô tả sự việc.',
            'type' => 'Cướp giật',
            'status' => 'approved',
            'location' => 'Ngã tư B, Quận 1',
            'latitude' => 10.7769,
            'longitude' => 106.7009,
        ]);
    }

    public function test_edit_page_loads_the_shared_positionable_map(): void
    {
        $owner = $this->verifiedUser();
        $alert = $this->alertFor($owner);

        $html = $this->actingAs($owner)
            ->get("/alerts/{$alert->id}/edit")
            ->assertOk()
            ->getContent();

        // Both pages must build the map from the same module. The old inline
        // block duplicated the logic and drifted; a second copy is how this
        // bug happened.
        $this->assertStringContainsString('js/alert_map_picker.js', $html);

        // The marker fix the inline copy omitted. Without it Leaflet 1.9
        // renders no marker at all, so the user sees a bare map.
        $this->assertStringContainsString('fixLeafletIcons()', $html);
    }

    public function test_edit_page_ships_the_geocoder_and_location_field(): void
    {
        $owner = $this->verifiedUser();
        $alert = $this->alertFor($owner);

        $html = $this->actingAs($owner)
            ->get("/alerts/{$alert->id}/edit")
            ->assertOk()
            ->getContent();

        // Same search box as the create page — without it there is no way to
        // enter an address on this page, only drag a marker.
        $this->assertStringContainsString('Control.Geocoder.js', $html);

        // The address field, seeded with the stored location rather than
        // empty. The old form persisted whatever coordinates the user clicked
        // while leaving the stale address untouched.
        $dom = $this->dom($html);
        $xp = new \DOMXPath($dom);
        $location = $xp->query('//input[@id="location"]');
        $this->assertSame(1, $location->length, 'the location input must render once');
        $this->assertSame('Ngã tư B, Quận 1', $location->item(0)->getAttribute('value'));
    }

    public function test_create_page_and_edit_page_load_the_same_picker(): void
    {
        // The strongest guard against a third divergent copy: assert the two
        // pages reference one file, not two copies of it.
        $owner = $this->verifiedUser();

        $create = $this->actingAs($owner)->get('/alerts/create')->assertOk()->getContent();
        $this->assertStringContainsString('js/alert_map_picker.js', $create);
        $this->assertStringContainsString('js/alerts_create.js', $create);

        // The edit page must not carry the inline map block it used to.
        $alert = $this->alertFor($owner);
        $edit = $this->actingAs($owner)->get("/alerts/{$alert->id}/edit")->assertOk()->getContent();
        $this->assertStringContainsString('js/alert_map_picker.js', $edit);
        $this->assertStringNotContainsString("L.map('map').setView([10.762622", $edit);
    }

    public function test_no_empty_asset_is_referenced_anywhere(): void
    {
        // css/app.css, css/news.css, js/app.js and css/alerts_map.css were
        // empty (0 bytes, or a single space) but still requested on every
        // page load. The favicon.ico was likewise empty and unreferenced
        // after #347 replaced it with favicon.svg.
        $pages = [
            ['/', null],
            ['/login', null],
            ['/register', null],
            ['/news', null],
            ['/wanted-list', null],
            ['/alerts', 'auth'],
            ['/alerts/map', 'auth'],
            ['/dashboard', 'auth'],
        ];

        $dead = ['css/app.css', 'js/app.js', 'css/news.css', 'css/alerts_map.css', 'favicon.ico'];

        foreach ($pages as [$uri, $guard]) {
            $request = $guard === 'auth'
                ? $this->actingAs($this->verifiedUser())->get($uri)
                : $this->get($uri);

            $html = $request->assertOk()->getContent();

            foreach ($dead as $asset) {
                $this->assertStringNotContainsString(
                    $asset,
                    $html,
                    "{$uri} must not request the empty asset {$asset}."
                );
            }
        }
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();

        return $dom;
    }
}
