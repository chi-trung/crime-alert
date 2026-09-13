<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #67: /my-history rendered every row the user ever posted in one
 * page. Both tables are now paginated with independent page parameters so
 * paging one table cannot move the other.
 */
class MyHistoryPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_tables_are_capped_at_ten_per_page(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 11) as $i) {
            Alert::create(['user_id' => $user->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'approved']);
            Experience::create(['user_id' => $user->id, 'name' => 'N', 'title' => "E{$i}", 'content' => 'c', 'status' => 'approved']);
        }

        $this->actingAs($user)->get('/my-history')
            ->assertOk()
            ->assertViewHas('myAlerts', fn ($pager) => $pager->count() === 10 && $pager->total() === 11)
            ->assertViewHas('myExperiences', fn ($pager) => $pager->count() === 10 && $pager->total() === 11);
    }

    public function test_the_two_tables_page_independently(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 11) as $i) {
            Alert::create(['user_id' => $user->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'approved']);
            Experience::create(['user_id' => $user->id, 'name' => 'N', 'title' => "E{$i}", 'content' => 'c', 'status' => 'approved']);
        }

        // Rows land in the same created_at second, so only the page-2 size is
        // deterministic (which row sorts out of a 10-way tie is up to the DB).
        // The point is that each table follows its own parameter.
        $this->actingAs($user)->get('/my-history?alerts_page=2')
            ->assertOk()
            ->assertViewHas('myAlerts', fn ($pager) => $pager->count() === 1 && $pager->currentPage() === 2)
            ->assertViewHas('myExperiences', fn ($pager) => $pager->count() === 10 && $pager->currentPage() === 1);

        $this->actingAs($user)->get('/my-history?exp_page=2')
            ->assertOk()
            ->assertViewHas('myExperiences', fn ($pager) => $pager->count() === 1 && $pager->currentPage() === 2)
            ->assertViewHas('myAlerts', fn ($pager) => $pager->count() === 10 && $pager->currentPage() === 1);
    }

    public function test_pager_links_carry_the_query_string(): void
    {
        // The page-2 links must keep the other table's page parameter, or
        // clicking one resets its neighbour.
        $user = User::factory()->create();
        foreach (range(1, 11) as $i) {
            Alert::create(['user_id' => $user->id, 'title' => "A{$i}", 'description' => 'd', 'status' => 'approved']);
        }

        $this->actingAs($user)->get('/my-history?exp_page=3')
            ->assertOk()
            ->assertSee('alerts_page=2', false)
            ->assertSee('exp_page=3', false);
    }
}
