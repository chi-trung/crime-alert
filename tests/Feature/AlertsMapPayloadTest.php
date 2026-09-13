<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #79: mapView selected every column of every approved, geocoded alert
 * and the blade @json'd the whole model set into window.ALERTS_DATA — so
 * description text, image paths, user_id, the always-'approved' status and
 * both timestamps rode to each viewer. The six fields alerts_map.js actually
 * reads are now the only six columns selected.
 */
class AlertsMapPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const LIVE_FIELDS = ['id', 'title', 'type', 'location', 'latitude', 'longitude'];

    private const DEAD_FIELDS = ['description', 'image', 'user_id', 'status', 'created_at', 'updated_at'];

    private function seedAlert(): Alert
    {
        $user = User::factory()->create();

        return Alert::create([
            'user_id' => $user->id,
            'title' => 'Trộm xe máy',
            'description' => 'Mô tả rất dài để nếu cột này lọt vào payload thì chuỗi tìm kiếm dưới đây chắc chắn thấy nó: CANARY-DESCRIPTION',
            'location' => 'Quận 1',
            'image' => 'alerts/canary-image.jpg',
            'status' => 'approved',
            'type' => 'Trộm cắp',
            'latitude' => 10.7769,
            'longitude' => 106.7009,
        ]);
    }

    public function test_map_query_selects_only_the_consumed_columns(): void
    {
        $this->seedAlert();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/alerts/map')
            ->assertOk()
            ->assertViewHas('alerts', function ($alerts) {
                $alert = $alerts->first();
                // Positive control: the read must still be a non-empty set of
                // rows carrying the six live fields, so the negatives below
                // can't pass by emptying the collection.
                $this->assertCount(1, $alerts);
                foreach (self::LIVE_FIELDS as $field) {
                    $this->assertArrayHasKey($field, $alert->getAttributes(), "live field {$field} missing from mapView");
                }
                foreach (self::DEAD_FIELDS as $field) {
                    $this->assertArrayNotHasKey($field, $alert->getAttributes(), "column {$field} is no longer read by any consumer");
                }

                return true;
            });
    }

    public function test_serialized_payload_carries_no_dead_columns(): void
    {
        $this->seedAlert();
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/alerts/map')
            ->assertOk()
            ->getContent();

        // Blade's @json hex-escapes quotes and unicode-escapes non-ASCII, so
        // string-matching the raw HTML is unreliable — extract the assignment
        // and decode what actually ships. The escaped form is valid JSON.
        $this->assertMatchesRegularExpression('/window\.ALERTS_DATA = \[.*?\];/s', $html, 'ALERTS_DATA assignment vanished from the page');
        preg_match('/window\.ALERTS_DATA = (\[.*?\]);/s', $html, $m);
        $rows = json_decode($m[1], true);
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows, 'positive control: the alert must still reach the payload');

        // Exact key list — the strongest statement of the fix: no
        // description/image/user_id/status/timestamps, not even as null.
        $this->assertSame(self::LIVE_FIELDS, array_keys($rows[0]));
    }

    public function test_alerts_the_map_does_not_show_are_not_read_at_all(): void
    {
        // A pending alert and a geocoded-less approved alert must not even
        // land in the selected-columns query's result set.
        $user = User::factory()->create();
        Alert::create([
            'user_id' => $user->id, 'title' => 'pending', 'description' => 'd',
            'status' => 'pending', 'latitude' => 10.0, 'longitude' => 106.0,
        ]);
        Alert::create([
            'user_id' => $user->id, 'title' => 'nogeo', 'description' => 'd',
            'status' => 'approved',
        ]);

        $this->actingAs($user)->get('/alerts/map')
            ->assertOk()
            ->assertViewHas('alerts', fn ($alerts) => $alerts->count() === 0);
    }
}
