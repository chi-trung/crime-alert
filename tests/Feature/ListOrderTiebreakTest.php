<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #81: these paginators ordered by created_at alone. created_at is
 * second-resolution, so same-second rows (a posting burst, scripted bulk
 * submits) have no deterministic order — page boundaries shuffle between
 * refreshes and a viewer sees a row twice or misses one. The #75 idiom is
 * the id tiebreak; here it covers alerts index + adminIndex, experiences
 * index + adminIndex, my-history's two tables, and the notifications list
 * (whose framework ->latest() is equally tiebreak-less).
 */
class ListOrderTiebreakTest extends TestCase
{
    use RefreshDatabase;

    private function seedAlerts(User $user, int $n, string $status = 'approved'): void
    {
        for ($i = 1; $i <= $n; $i++) {
            // forceCreate keeps every row in the SAME created_at second
            // (and created_at isn't fillable, so create() would drop an
            // explicit one) — that shared instant is exactly the ambiguity
            // the tiebreak resolves.
            Alert::forceCreate([
                'user_id' => $user->id,
                'title' => "A{$i}",
                'description' => 'd',
                'status' => $status,
            ]);
        }
    }

    private function seedExperiences(User $user, int $n, string $status = 'approved'): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Experience::forceCreate([
                'user_id' => $user->id,
                'name' => 'N',
                'title' => "E{$i}",
                'content' => 'd',
                'status' => $status,
            ]);
        }
    }

    /**
     * A dialect-tolerant check that some query on the current request ended
     * `created_at desc, id desc`. ORDER BY legs carry no bindings, so
     * string-matching the logged SQL is safe here (unlike matching literals
     * in the where clause). Covers sqlite double-quotes and mysql backticks.
     */
    private function assertSomeQueryTieBreaksById(): void
    {
        $re = '/order by\s+["`\']?created_at["`\']?\s+desc\s*,\s*["`\']?id["`\']?\s+desc/i';
        $hit = false;
        $log = DB::getQueryLog();
        foreach ($log as $entry) {
            if (preg_match($re, $entry['query'])) {
                $hit = true;
                break;
            }
        }
        $this->assertTrue($hit, 'no query on this request carried the created_at desc, id desc tiebreak; log had '.count($log).' entr(y)');
    }

    public function test_public_alerts_page_two_holds_the_oldest_row(): void
    {
        $user = User::factory()->create();
        $this->seedAlerts($user, 11);
        $oldestId = Alert::min('id');

        // With the tiebreak, newest-first puts the lowest id on the last
        // page. Without it, sqlite preserves insertion (id-ascending) order
        // across the equal created_at, so page 2 lands on the NEWEST id.
        $this->actingAs($user)->get('/alerts?page=2')
            ->assertOk()
            ->assertViewHas('alerts', fn ($pager) => [$oldestId] === $pager->pluck('id')->all());
    }

    public function test_experiences_page_two_holds_the_oldest_row(): void
    {
        $user = User::factory()->create();
        $this->seedExperiences($user, 10);
        $oldestId = Experience::min('id');

        $this->get('/experiences?page=2')
            ->assertOk()
            ->assertViewHas('experiences', fn ($pager) => [$oldestId] === $pager->pluck('id')->all());
    }

    public function test_my_history_both_tables_tiebreak_by_id(): void
    {
        $user = User::factory()->create();
        $this->seedAlerts($user, 11, 'pending');
        $this->seedExperiences($user, 11, 'pending');
        $oldestAlert = Alert::min('id');
        $oldestExp = Experience::min('id');

        $this->actingAs($user)->get('/my-history?alerts_page=2&exp_page=2')
            ->assertOk()
            ->assertViewHas('myAlerts', fn ($pager) => [$oldestAlert] === $pager->pluck('id')->all())
            ->assertViewHas('myExperiences', fn ($pager) => [$oldestExp] === $pager->pluck('id')->all());
    }

    public function test_admin_alerts_page_two_holds_the_oldest_row(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedAlerts($admin, 16);
        $oldestId = Alert::min('id');

        $this->actingAs($admin)->get('/admin/alerts?page=2')
            ->assertOk()
            ->assertViewHas('alerts', fn ($pager) => [$oldestId] === $pager->pluck('id')->all());
    }

    public function test_admin_experiences_page_two_holds_the_oldest_row(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedExperiences($admin, 16);
        $oldestId = Experience::min('id');

        $this->actingAs($admin)->get('/admin/experiences?page=2')
            ->assertOk()
            ->assertViewHas('experiences', fn ($pager) => [$oldestId] === $pager->pluck('id')->all());
    }

    public function test_notifications_query_orders_by_created_at_then_id(): void
    {
        // The framework DatabaseNotification primary key is a uuid string, so
        // a page-membership-by-id assertion is meaningless (no insertion
        // order). Pin the ORDER BY leg the controller now adds instead.
        $user = User::factory()->create();
        $alert = Alert::forceCreate(['user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $user->notify(new NewPostNotification($alert, $user, 'alert'));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get('/notifications')->assertOk();
        $this->assertSomeQueryTieBreaksById();
    }
}
