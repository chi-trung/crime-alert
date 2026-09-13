<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #75: both support lists rendered every request ever filed. Same
 * read-side class as #67 (my-history) and #71 (dashboard payload): an
 * unbounded ->get() feeding a page. Both are paginated now, with the
 * established hasPages()-guarded bootstrap-4 pager.
 */
class SupportListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRequests(User $user, int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            SupportRequest::create(['user_id' => $user->id, 'subject' => "S{$i}"]);
        }
    }

    public function test_user_list_is_capped_at_ten_per_page(): void
    {
        $user = User::factory()->create();
        $this->seedRequests($user, 11);

        $this->actingAs($user)->get('/support')
            ->assertOk()
            ->assertViewHas('requests', fn ($pager) => $pager->count() === 10 && $pager->total() === 11);
    }

    public function test_admin_list_is_capped_at_fifteen_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->seedRequests($user, 16);

        $this->actingAs($admin)->get('/admin/support')
            ->assertOk()
            ->assertViewHas('requests', fn ($pager) => $pager->count() === 15 && $pager->total() === 16);
    }

    public function test_second_page_carries_the_remainder_in_id_order(): void
    {
        // created_at is second-resolution, so with 11 rows in one instant the
        // only deterministic statement is page size + page number + the id
        // tiebreak (which is why the tiebreak exists): newest-first, so page
        // 2 holds the oldest row.
        $user = User::factory()->create();
        $this->seedRequests($user, 11);
        $oldestId = SupportRequest::where('user_id', $user->id)->min('id');

        $this->actingAs($user)->get('/support?page=2')
            ->assertOk()
            ->assertViewHas('requests', fn ($pager) => $pager->currentPage() === 2
                && $pager->count() === 1
                && $pager->first()->id === $oldestId);
    }

    public function test_single_page_lists_render_no_pager(): void
    {
        $user = User::factory()->create();
        $this->seedRequests($user, 1);

        $this->actingAs($user)->get('/support')
            ->assertOk()
            ->assertViewHas('requests', fn ($pager) => $pager->hasPages() === false)
            ->assertDontSee('page-link', false);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/support')
            ->assertOk()
            ->assertViewHas('requests', fn ($pager) => $pager->hasPages() === false)
            ->assertDontSee('page-link', false);
    }
}
