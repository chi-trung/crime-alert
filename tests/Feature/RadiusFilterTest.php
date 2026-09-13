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
}
