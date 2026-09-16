<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use App\Models\WantedPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * Issue #317 (r15/pagination-bounds): the framework's default current-page
 * resolver accepts ANY int64-fitting ?page, and LengthAwarePaginator::firstItem
 * then computes (page-1)*perPage+1 — which overflows int64 to a float and
 * Blade echoes scientific notation into the page. Probe signatures on main,
 * identical on sqlite and mysql:
 *   /wanted-list?page=9223372036854775800  -> 200, 20 real rows, STT cell
 *       literally "1.844674407371E+20"
 *   /admin/alerts?page=9223372036854775800 -> footer "Hiển thị
 *       1.3835058055282E+20 đến 1.3835058055282E+20 trong 3 kết quả"
 * A page above lastPage but inside int range (?page=20 of 3) was the twin:
 * the slice query returns zero rows while total() stays 3, so the #227
 * footer guard (total() > 0) passed and rendered the blank-span sentence
 * "Hiển thị  đến  trong 3 kết quả" — and every such request fired a real
 * huge-OFFSET SELECT (which sqlite answers by returning page-1 rows anyway,
 * proving the OFFSET value is already nonsense before rendering).
 *
 * BoundedPaginator resolves the page exactly as the framework would, counts
 * the filtered total once, and clamps to [1, lastPage] before the slice
 * query — wired into all eleven ->paginate() call sites in
 * app/Http/Controllers. The footer additionally guards on count() (what it
 * describes) instead of total() (what it only mentions).
 */
class BoundedPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function seedWantedPeople(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            WantedPerson::create([
                'name' => 'P'.$i, 'birth_year' => 1990, 'address' => 'x', 'parents' => 'p',
                'decision_number' => (string) $i, 'decision_date' => '2024-01-01', 'crime' => 'c',
                'unit' => 'u', 'wanted_type' => 'tc',
            ]);
        }
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** The literal probe signature: float-notation digits anywhere in the body. */
    private function assertNoFloatNotation(string $html): void
    {
        preg_match('/\d+E\+\d+/', $html, $m);
        $this->assertSame([], $m, 'scientific-notation page arithmetic leaked into the HTML: '.($m[0] ?? ''));
    }

    public function test_wanted_list_overflow_page_clamps_to_the_last_page_instead_of_printing_e_plus_20(): void
    {
        // 25 people over 20 per page: the real last page is 2, whose first
        // STT cell is 21. Pre-fix this request returned 20 rows whose STT
        // column began "1.844674407371E+20".
        $this->seedWantedPeople(25);

        $html = $this->get('/wanted-list?page=9223372036854775800')
            ->assertOk()
            ->getContent();

        $this->assertNoFloatNotation($html);
        preg_match('/<td>([^<]*)<\/td>/', $html, $m);
        $this->assertSame('21', trim($m[1]), 'clamped page 2 must start at sequence 21');
        $this->assertSame(5, preg_match_all('/<td>P\d+<\/td>/', $html), 'clamped page holds the last 5 people');
    }

    public function test_admin_alerts_footer_never_prints_float_page_arithmetic(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Alert::create(['user_id' => $user->id, 'title' => 'A'.$i, 'description' => 'd', 'status' => 'approved']);
        }

        $html = $this->actingAs($this->admin())
            ->get('/admin/alerts?page=9223372036854775800')
            ->assertOk()
            ->getContent();

        $this->assertNoFloatNotation($html);
        // The whole table fits one page, so the clamped footer is 1..3 of 3.
        // \s+ between the fragments because the blade itself breaks the
        // sentence across lines (whitespace-collapsed match).
        $this->assertMatchesRegularExpression(
            '/Hiển thị\s*<span[^>]*>1<\/span>\s*đến\s*<span[^>]*>3<\/span>\s*trong\s*<span[^>]*>3<\/span>\s*kết\s*quả/u',
            $html
        );
    }

    public function test_a_past_end_int_page_loses_the_blank_span_footer_sentence(): void
    {
        // ?page=20 of 3: pre-fix the slice was empty but total() was 3, so
        // the #227 guard passed and the footer printed two BLANK spans:
        // "Hiển thị  đến  trong 3 kết quả". Post-clamp the request answers
        // the last real page instead.
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            Alert::create(['user_id' => $user->id, 'title' => 'A'.$i, 'description' => 'd', 'status' => 'approved']);
        }

        $html = $this->actingAs($this->admin())
            ->get('/admin/alerts?page=20')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Hiển thị <span class="fw-semibold"></span>', $html);
        $this->assertMatchesRegularExpression(
            '/Hiển thị\s*<span[^>]*>1<\/span>\s*đến\s*<span[^>]*>3<\/span>\s*trong\s*<span[^>]*>3<\/span>\s*kết\s*quả/u',
            $html
        );
    }

    public function test_the_footer_guard_tracks_the_current_slice_not_the_total(): void
    {
        // Pins the blade change itself: a total()>0-but-count()==0 paginator
        // (now unreachable through the controllers, reachable through any
        // future unclamped call site) must render NO "Hiển thị" sentence —
        // pre-#317 the guard let it print two blank spans plus "trong N kết
        // quả". Built by hand so the test does not depend on the clamp.
        $this->actingAs($this->admin());
        $page20 = new LengthAwarePaginator([], 3, 15, 20, ['path' => '/admin/alerts', 'pageName' => 'page']);

        $html = view('alerts.admin_index', ['alerts' => $page20])->render();

        $this->assertStringNotContainsString('Hiển thị', $html);
    }

    public function test_clamping_does_not_disturb_ordinary_pages(): void
    {
        // Regression pin against over-clamping: page 1 and page 2 still show
        // their own slices with correct STT starts, and hostile-but-coerced
        // values (0, non-numeric) stay on page 1 as before.
        $this->seedWantedPeople(25);

        $first = $this->get('/wanted-list')->assertOk()->getContent();
        $this->assertSame(20, preg_match_all('/<td>P\d+<\/td>/', $first));
        preg_match('/<td>([^<]*)<\/td>/', $first, $m);
        $this->assertSame('1', trim($m[1]));

        $second = $this->get('/wanted-list?page=2')->assertOk()->getContent();
        $this->assertSame(5, preg_match_all('/<td>P\d+<\/td>/', $second));
        preg_match('/<td>([^<]*)<\/td>/', $second, $m);
        $this->assertSame('21', trim($m[1]));

        foreach (['0', 'abc', '-5'] as $bad) {
            $html = $this->get('/wanted-list?page='.$bad)->assertOk()->getContent();
            preg_match('/<td>([^<]*)<\/td>/', $html, $m);
            $this->assertSame('1', trim($m[1]), "page={$bad} must answer page 1");
            $this->assertNoFloatNotation($html);
        }
    }

    public function test_my_history_overflow_clamps_both_page_lanes(): void
    {
        // The two tables share one URL with distinct page names; a flood of
        // 9223372036854775800 on either lane used to reach that table's
        // pagination links with the overflowed page number.
        $user = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            Alert::create(['user_id' => $user->id, 'title' => 'A'.$i, 'description' => 'd', 'status' => 'approved']);
            Experience::create(['user_id' => $user->id, 'name' => 'N', 'title' => 'T'.$i, 'content' => 'c', 'status' => 'approved']);
        }

        $html = $this->actingAs($user)
            ->get('/my-history?alerts_page=9223372036854775800&exp_page=9223372036854775800')
            ->assertOk()
            ->getContent();

        $this->assertNoFloatNotation($html);
        // Clamped to each lane's last real page (12 rows / 10 per page = 2,
        // ordered id-desc so page 2 holds the OLDEST two rows per table) —
        // the @empty "chưa đăng" cell must be gone, the oldest titles present,
        // and each pager marks page 2 active. The tables render explicitly
        // through links('pagination::bootstrap-4'), whose active-page element
        // is <li class="page-item active" aria-current="page"><span
        // class="page-link">N</span></li> — that markup is pinned here.
        $this->assertStringNotContainsString('Bạn chưa đăng cảnh báo nào', $html);
        $this->assertStringNotContainsString('Bạn chưa có bài chia sẻ nào', $html);
        $this->assertStringContainsString('<td class="fw-semibold">A0</td>', $html);
        $this->assertStringContainsString('<td class="fw-semibold">T0</td>', $html);
        $this->assertSame(
            2,
            substr_count($html, 'aria-current="page"><span class="page-link">2</span>'),
            'both pagers sit on their clamped page 2'
        );
    }

    public function test_no_controller_paginates_through_the_bare_framework_path(): void
    {
        // #311's sync-pin doctrine: the defect is in page RESOLUTION, so any
        // bare ->paginate() reopens every URL in this class. Every call site
        // must ride BoundedPaginator.
        $offenders = [];
        foreach (glob(app_path('Http/Controllers/*.php')) as $file) {
            $src = file_get_contents($file);
            if (preg_match('/->paginate\(/', $src) && ! str_contains($src, 'BoundedPaginator::paginate')) {
                $offenders[] = basename($file);
            }
            if (str_contains($src, 'BoundedPaginator::paginate(') && substr_count($src, '->paginate(') > 0) {
                // helper call sites live on "BoundedPaginator::paginate(" lines;
                // any remaining bare occurrence is an offender
                foreach (explode("\n", $src) as $line) {
                    if (preg_match('/->paginate\(/', $line) && ! str_contains($line, 'BoundedPaginator::paginate')) {
                        $offenders[] = basename($file).': '.trim($line);
                    }
                }
            }
        }
        $this->assertSame([], $offenders, 'unbounded ->paginate() call sites: '.implode(' | ', $offenders));
    }
}
