<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #227: the moderation table's footer called $alerts->firstItem() and
 * lastItem() unguarded. On an empty result set both return null (Laravel's
 * LengthAwarePaginator documents null for "no items"), so with zero alerts —
 * a fresh install, or every row filtered away — /admin/alerts rendered the
 * sentence shell "Hiển thị  đến  trong  0 kết quả" with two blank
 * fw-semibold spans and no links() block, under the same page that already
 * shows the "Tạo cảnh báo mới" empty-state CTA.
 */
class AdminAlertsEmptyFooterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_empty_list_does_not_render_the_blank_summary_sentence(): void
    {
        $html = $this->actingAs($this->admin())
            ->get('/admin/alerts')
            ->assertOk()
            ->getContent();

        // The whole footer line disappears — not just its numbers, which
        // would still leave "Hiển thị  đến  trong" printed.
        $this->assertStringNotContainsString('Hiển thị', $html);
        $this->assertStringNotContainsString('kết quả', $html);
        // The spans themselves must be gone, not merely emptied.
        $this->assertStringNotContainsString('fw-semibold"></span>', $html);
        // The CTA empty state is still what greets an empty queue.
        $this->assertStringContainsString('Tạo cảnh báo mới', $html);
    }

    public function test_nonempty_list_still_shows_the_summary(): void
    {
        $admin = $this->admin();
        foreach (['pending', 'approved'] as $status) {
            Alert::forceCreate([
                'user_id' => $admin->id,
                'title' => "A-{$status}",
                'description' => 'd',
                'status' => $status,
            ]);
        }

        $html = $this->actingAs($admin)
            ->get('/admin/alerts')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Hiển thị <span class="fw-semibold">1<\/span> đến\s*<span class="fw-semibold">2<\/span> trong\s*<span class="fw-semibold">2<\/span> kết quả/',
            $html,
            'the footer must return with real numbers once rows exist'
        );
    }

    public function test_single_page_omits_the_links_block_but_keeps_the_summary(): void
    {
        // paginate(15) with one row: links() renders nothing on a single
        // page, which the old markup left as an empty flex child — the fix
        // keeps the sentence (it has real numbers now) and this pins that
        // the two states are not conflated.
        $admin = $this->admin();
        Alert::forceCreate([
            'user_id' => $admin->id,
            'title' => 'Chỉ một',
            'description' => 'd',
            'status' => 'pending',
        ]);

        $html = $this->actingAs($admin)
            ->get('/admin/alerts')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Hiển thị <span class="fw-semibold">1<\/span>.*?1<\/span> kết quả/s',
            $html
        );
        $this->assertStringNotContainsString('pagination', $html);
    }
}
