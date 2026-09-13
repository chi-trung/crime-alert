<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #59: the ?radius= filter was one crafted query string from a 500 —
 * it used acos/cos/sin, which SQLite doesn't provide, and re-selected
 * alerts.* inside the paginator's count(*) wrapper. It had never been
 * exercised: no test and no view links to it.
 */
class RadiusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function alertAt(User $user, string $title, float $lat, float $lng): Alert
    {
        return Alert::create([
            'user_id' => $user->id, 'title' => $title, 'description' => 'd',
            'status' => 'approved', 'latitude' => $lat, 'longitude' => $lng,
        ]);
    }

    public function test_radius_filter_returns_200_and_keeps_only_alerts_inside_the_circle(): void
    {
        $user = User::factory()->create();
        // Around Hanoi (21.03, 105.85): inside ~50km, outside ~300km.
        $near = $this->alertAt($user, 'RadiusNearOne', 21.03, 105.85);
        $alsoNear = $this->alertAt($user, 'RadiusNearTwo', 20.42, 106.16); // ~60km
        $far = $this->alertAt($user, 'RadiusFarAway', 10.82, 106.63);        // ~1700km

        $response = $this->actingAs($user)
            ->get('/alerts?lat=21.03&lng=105.85&radius=100')
            ->assertOk(); // used to be a QueryException / 500

        $response->assertSee($near->title)
            ->assertSee($alsoNear->title)
            ->assertDontSee($far->title);
    }

    public function test_radius_filter_orders_nearest_first(): void
    {
        $user = User::factory()->create();
        $here = $this->alertAt($user, 'RadiusFirst', 21.03, 105.85);   // 0 km
        $mid = $this->alertAt($user, 'RadiusSecond', 20.7, 106.0);       // ~40 km
        $edge = $this->alertAt($user, 'RadiusThird', 20.4, 106.2);       // ~80 km

        $html = $this->actingAs($user)
            ->get('/alerts?lat=21.03&lng=105.85&radius=100')
            ->assertOk()->getContent();

        $this->assertLessThan(strpos($html, $mid->title), strpos($html, $here->title));
        $this->assertLessThan(strpos($html, $edge->title), strpos($html, $mid->title));
    }

    public function test_alerts_without_coordinates_are_excluded_even_when_inside(): void
    {
        $user = User::factory()->create();
        $noCoords = Alert::create(['user_id' => $user->id, 'title' => 'RadiusNoCoords', 'description' => 'd', 'status' => 'approved']);
        $withCoords = $this->alertAt($user, 'RadiusHasCoords', 21.03, 105.85);

        $this->actingAs($user)->get('/alerts?lat=21.03&lng=105.85&radius=100')
            ->assertOk()
            ->assertDontSee($noCoords->title)
            ->assertSee($withCoords->title);
    }

    public function test_index_without_radius_is_untouched(): void
    {
        $user = User::factory()->create();
        $far = $this->alertAt($user, 'RadiusPlainList', 10.82, 106.63);

        $this->actingAs($user)->get('/alerts')->assertOk()->assertSee($far->title);
    }

    public function test_non_finite_radius_inputs_answer_200_instead_of_500(): void
    {
        // Issue #127: 1e999 casts to INF, cos(deg2rad(INF)) is NAN, and
        // sprintf('%.6F') emits the bare word NaN into the raw SQL — a 1054
        // / "no such column" QueryException on both CI engines. #59 clamped
        // radius' range but not its finiteness and left lat/lng bare.
        $user = User::factory()->create();
        $here = $this->alertAt($user, 'RadiusFiniteStillWorks', 21.03, 105.85);

        foreach (['1e999', '-1e999'] as $junk) {
            foreach (['lat' => $junk, 'lng' => $junk, 'radius' => $junk] as $param => $value) {
                $this->actingAs($user)
                    ->get('/alerts?radius=100&lat=21.03&lng=105.85&'.$param.'='.$value)
                    ->assertOk();
            }
        }

        // The word "nan" parses platform-dependent ((float)"nan" is NAN on
        // glibc, 0.0 on the Windows CRT), so only the post-fix contract is
        // assertable cross-engine: it must answer, not throw.
        $this->actingAs($user)->get('/alerts?radius=100&lat=nan&lng=105.85')->assertOk();
        $this->actingAs($user)->get('/alerts?radius=nan&lat=21.03&lng=105.85')->assertOk();

        // A junk center clamps to 0 (like #59's radius fallback): the circle
        // at (0, 105.85) is thousands of km from Hanoi, so nothing matches —
        // but the request answers with a page instead of a QueryException.
        $this->actingAs($user)->get('/alerts?radius=100&lat=1e999&lng=105.85')
            ->assertOk()->assertDontSee($here->title);
    }

    public function test_out_of_range_finite_radius_inputs_are_clamped_not_rejected(): void
    {
        // lat=999 is finite; it falls outside [-90,90] and clamps to the pole
        // rather than 500ing or interpolating. The near alert (~700km from
        // the clamped pole center) stays outside a 100km circle.
        $user = User::factory()->create();
        $this->alertAt($user, 'RadiusClampBoundary', 21.03, 105.85);

        $this->actingAs($user)->get('/alerts?radius=100&lat=999&lng=105.85')
            ->assertOk()->assertDontSee('RadiusClampBoundary');
        $this->actingAs($user)->get('/alerts?radius=100&lat=21.03&lng=-99999')
            ->assertOk()->assertDontSee('RadiusClampBoundary');
        // radius clamps to the half-circumference cap: every alert is inside.
        $this->actingAs($user)->get('/alerts?radius=99999999&lat=21.03&lng=105.85')
            ->assertOk()->assertSee('RadiusClampBoundary');
    }
}
