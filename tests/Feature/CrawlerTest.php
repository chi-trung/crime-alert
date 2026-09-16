<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\WantedPerson;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrawlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_crawl_creates_and_updates_without_wiping_published_at(): void
    {
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response('<div class="item-news">
                <h3 class="title-news"><a href="/phap-luat/tin-a-123.html">Tieu de tin</a></h3>
                <p class="description">Mo ta ngan</p>
            </div>', 200),
        ]);

        // Pre-existing row with a manually maintained publish date.
        $news = News::create([
            'title' => 'Cu',
            'description' => 'cu',
            'link' => 'https://vnexpress.net/phap-luat/tin-a-123.html',
            'published_at' => '2024-01-02 03:04:05',
        ]);

        $this->artisan('crawl:news')->assertSuccessful();

        $news->refresh();
        $this->assertSame('Tieu de tin', $news->title);
        // Regression: re-crawl used to force published_at back to null.
        $this->assertNotNull($news->published_at);

        // A fresh link lands with null published_at and the relative URL absolutized.
        $this->assertDatabaseHas('news', [
            'link' => 'https://vnexpress.net/phap-luat/tin-a-123.html',
            'title' => 'Tieu de tin',
        ]);
    }

    /**
     * Issue #181: Crawler::attr() returns null for an anchor without href,
     * and the null used to flow through strpos/mb_substr (PHP 8.1+ only
     * deprecates, Laravel's null deprecations channel hides it) into the
     * literal base domain — so every href-less article collapsed into ONE
     * fake row keyed link='https://vnexpress.net' on the UNIQUE column while
     * the run still reported success. Href-less items are now skipped and
     * surfaced by a warn line, never written.
     */
    public function test_news_crawl_skips_href_less_articles_instead_of_collapsing_them(): void
    {
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response(
                '<div class="item-news"><h3 class="title-news"><a>Tin A</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="">Tin Empty</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a>Tin B</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="/phap-luat/tin-c-789.html">Tin C</a></h3></div>',
                200
            ),
        ]);

        // Two href-less (null/empty) items must be skipped; the well-formed
        // relative-link item still lands.
        $this->artisan('crawl:news')->assertSuccessful();

        $this->assertSame(1, News::count());
        $this->assertDatabaseMissing('news', ['link' => 'https://vnexpress.net']);
        $this->assertDatabaseHas('news', [
            'link' => 'https://vnexpress.net/phap-luat/tin-c-789.html',
            'title' => 'Tin C',
        ]);
    }

    public function test_news_crawl_fails_gracefully_on_network_error(): void
    {
        Http::fake([
            'vnexpress.net/*' => Http::response(null, 503),
        ]);

        // Must warn and return FAILURE without throwing.
        $this->artisan('crawl:news')->assertFailed();
        $this->assertSame(0, News::count());
    }

    public function test_news_crawl_fails_gracefully_when_host_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: failed to connect'));

        $this->artisan('crawl:news')->assertFailed();
        $this->assertSame(0, News::count());
    }

    public function test_wanted_list_crawl_parses_rows(): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response('<table>
                <tr><td>STT</td><td>Họ tên</td><td>Năm sinh</td><td>Địa chỉ</td><td>Cha/Mẹ</td><td>Tội danh</td><td>Quyết định</td><td>Cơ quan</td></tr>
                <tr><td>1</td><td>Nguyen Van A</td><td>1990</td><td>Ha Noi</td><td>Van B</td><td>Lua dao</td><td>QD-1</td><td>C03</td></tr>
                <tr><td>2</td><td>Tran Thi C</td><td>1985</td><td>Nam Dinh</td><td>Van D</td><td>Trom cap</td><td>QD-2</td><td>PC03</td></tr>
            </table>', 200),
        ]);

        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $this->assertDatabaseHas('wanted_people', [
            'name' => 'Nguyen Van A',
            'birth_year' => '1990',
            'crime' => 'Lua dao',
        ]);
        $this->assertSame(2, WantedPerson::count());

        // Header row and malformed rows must be skipped, not inserted.
        $this->assertDatabaseMissing('wanted_people', ['name' => 'Họ tên']);
    }

    public function test_wanted_list_crawl_fails_gracefully_on_error(): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response(null, 500),
        ]);

        $this->artisan('crawl:wanted-list')->assertFailed();
        $this->assertSame(0, WantedPerson::count());
    }

    public function test_news_crawl_truncates_overlong_scraped_strings(): void
    {
        // Issue #100: title/link/image_url are VARCHAR(255) but scraped off
        // an untrusted DOM — one over-length value 500s every scheduled run
        // on MySQL. Bound asserted on the stored row so the test holds on
        // both CI dialects (SQLite would swallow the overflow silently).
        $longTitle = str_repeat('T', 400);
        $longPath = '/phap-luat/'.str_repeat('a', 400).'.html';
        $longImg = 'https://cdn.example/'.str_repeat('i', 400).'.jpg';
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response('<div class="item-news">
                <h3 class="title-news"><a href="'.$longPath.'">'.$longTitle.'</a></h3>
                <p class="description">Mo ta</p>
                <img src="'.$longImg.'">
            </div>', 200),
        ]);

        $this->artisan('crawl:news')->assertSuccessful();

        $news = News::sole();
        $this->assertSame(255, mb_strlen($news->title));
        $this->assertSame(255, mb_strlen($news->link));
        $this->assertSame(255, mb_strlen($news->image_url));
    }

    public function test_wanted_list_crawl_truncates_overlong_scraped_strings(): void
    {
        // Issue #106: same class as #100 (fixed for News in #101) — the
        // wanted-list crawler fed untrusted table cells straight into seven
        // VARCHAR(255) columns, 500ing every scheduled run on MySQL. Bound
        // asserted on the stored row so the test holds on both CI dialects
        // (SQLite would swallow the overflow silently).
        $long = str_repeat('X', 400);
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response('<table>
                <tr><td>STT</td><td>Họ tên</td><td>Năm sinh</td><td>Địa chỉ</td><td>Cha/Mẹ</td><td>Tội danh</td><td>Quyết định</td><td>Cơ quan</td></tr>
                <tr><td>1</td><td>'.$long.'</td><td>1990</td><td>'.$long.'</td><td>'.$long.'</td><td>'.$long.'</td><td>'.$long.'</td><td>'.$long.'</td></tr>
            </table>', 200),
        ]);

        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $person = WantedPerson::sole();
        $this->assertSame(255, mb_strlen($person->name));
        $this->assertSame(255, mb_strlen($person->address));
        $this->assertSame(255, mb_strlen($person->parents));
        $this->assertSame(255, mb_strlen($person->crime));
        $this->assertSame(255, mb_strlen($person->decision));
        $this->assertSame(255, mb_strlen($person->agency));
    }

    public function test_both_crawls_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($e) => $e->command);

        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'crawl:news')), 'crawl:news not scheduled');
        $this->assertTrue($events->contains(fn ($c) => str_contains($c, 'crawl:wanted-list')), 'crawl:wanted-list not scheduled');
    }

    /**
     * Issue #236: the listing is newest-first in document order, and every
     * crawled row keeps published_at NULL by #16's contract. Both feed
     * readers sort orderByDesc('published_at')->orderByDesc('id'), and NULL
     * ties under DESC on MySQL and SQLite alike, so the id tiebreak alone
     * orders the crawled block. Forward iteration gave the OLDEST article the
     * highest id (newest crawled news landed last on /news, despite the page
     * advertising "Cập nhật tin tức mới nhất"). Reverse iteration, the twin
     * CrawlWantedList's documented convention, inserts newest last so it
     * takes the highest id and the feed finally reads newest-first.
     */
    public function test_news_crawl_inserts_newest_last_so_the_id_tiebreak_orders_it_first(): void
    {
        // A three-article VnExpress-style listing: Newest-A first in the
        // document, Oldest-C last — exactly the order the live page ships.
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response(
                '<div class="item-news"><h3 class="title-news"><a href="/phap-luat/a.html">Newest-A</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="/phap-luat/b.html">Middle-B</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="/phap-luat/c.html">Oldest-C</a></h3></div>',
                200
            ),
        ]);

        $this->artisan('crawl:news')->assertSuccessful();
        $this->assertSame(3, News::count());

        // The writer-side contract: newest was inserted last, so it holds the
        // highest autoincrement id. Pinned directly because the reader below
        // depends on it.
        $byId = News::orderBy('id')->pluck('title')->all();
        $this->assertSame(['Oldest-C', 'Middle-B', 'Newest-A'], $byId);

        // The reader-side outcome the issue is about, through the REAL feed
        // controller (not a re-typed sort, which could drift from
        // NewsController::index and still pass): GET /news must surface the
        // all-NULL-published_at block newest-first via the id tiebreak.
        $titles = $this->get('/news')
            ->assertOk()
            ->viewData('news')
            ->pluck('title')
            ->all();
        $this->assertSame(['Newest-A', 'Middle-B', 'Oldest-C'], $titles);
    }

    /**
     * Issue #293 (CN-01a): #100's head-only mb_substr(0,255) runs BEFORE the
     * value becomes updateOrCreate's lookup key on the UNIQUE link column —
     * and VnExpress puts the discriminator that makes a link unique at the
     * END of the URL (the '-4839201.html' article id, '?zpage='/'&utm'
     * params). Two over-length articles sharing a 255-char prefix therefore
     * collapse onto one key: the second silently overwrites the first, one
     * article never exists in the DB, and $count still reports both — the
     * #181 collapse shape reopened through the #100 truncation path.
     */
    public function test_news_crawl_keeps_two_articles_whose_links_share_a_255_char_prefix(): void
    {
        $prefix = '/phap-luat/'.str_repeat('a', 300);
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response(
                '<div class="item-news"><h3 class="title-news"><a href="'.$prefix.'-1.html">Bai mot</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="'.$prefix.'-2.html">Bai hai</a></h3></div>',
                200
            ),
        ]);

        $this->artisan('crawl:news')->assertSuccessful();

        // Pre-fix: 1 row titled 'Bai mot' (reverse iteration upserts '-2'
        // first, '-1' overwrites it) and 'Bai hai' is gone forever.
        $this->assertSame(2, News::count());
        $this->assertDatabaseHas('news', ['title' => 'Bai mot']);
        $this->assertDatabaseHas('news', ['title' => 'Bai hai']);
    }

    public function test_news_crawl_digest_keys_stay_idempotent_across_runs(): void
    {
        // The digest must be a function of the URL, not of run order:
        // crawling the same over-length page twice updates in place rather
        // than minting fresh rows each pass.
        $prefix = '/phap-luat/'.str_repeat('a', 300);
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response(
                '<div class="item-news"><h3 class="title-news"><a href="'.$prefix.'-1.html">Bai mot</a></h3></div>'
                .'<div class="item-news"><h3 class="title-news"><a href="'.$prefix.'-2.html">Bai hai</a></h3></div>',
                200
            ),
        ]);

        $this->artisan('crawl:news')->assertSuccessful();
        $first = News::orderBy('id')->pluck('link')->all();

        $this->artisan('crawl:news')->assertSuccessful();
        $second = News::orderBy('id')->pluck('link')->all();

        $this->assertSame($first, $second);
        $this->assertSame(2, News::count());
    }

    /**
     * Issue #293 (CN-01b, the wanted-list twin): the (name, birth_year,
     * address) lookup key has no UNIQUE index, no decision number, and the
     * site's own data routinely carries same-named, same-age relatives at
     * one address. The second row overwrites the first: one fugitive
     * disappears from /wanted-list and the dashboard hotWanted tile, and the
     * surviving row's decision/crime/agency describe only one of them.
     */
    public function test_wanted_list_crawl_keeps_two_persons_sharing_name_year_and_address(): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response('<table>
                <tr><td>STT</td><td>Họ tên</td><td>Năm sinh</td><td>Địa chỉ</td><td>Cha/Mẹ</td><td>Tội danh</td><td>Quyết định</td><td>Cơ quan</td></tr>
                <tr><td>1</td><td>Nguyen Van A</td><td>1990</td><td>Ha Noi</td><td>Van B</td><td>Lua dao</td><td>QD-91</td><td>C03</td></tr>
                <tr><td>2</td><td>Nguyen Van A</td><td>1990</td><td>Ha Noi</td><td>Van C</td><td>Trom cap</td><td>QD-92</td><td>PC03</td></tr>
            </table>', 200),
        ]);

        $this->artisan('crawl:wanted-list')->assertSuccessful();

        $this->assertSame(2, WantedPerson::count());
        $this->assertDatabaseHas('wanted_people', ['decision' => 'QD-91', 'crime' => 'Lua dao']);
        $this->assertDatabaseHas('wanted_people', ['decision' => 'QD-92', 'crime' => 'Trom cap']);
    }

    /**
     * Issue #293 (CN-02): #181's promise ("a silent upstream markup change
     * surfaces as one warn line per run instead of vanishing") only holds
     * for href-less items — the $skipped counter lives INSIDE the per-item
     * loop, so when the '.item-news' selector itself matches nothing (a
     * class rename, the most likely markup change) the loop never runs, no
     * counter moves, and the run printed "Đã crawl xong 0 tin tức" + SUCCESS,
     * indistinguishable from a healthy crawl while the feed froze.
     */
    public function test_news_crawl_fails_loudly_when_the_listing_parses_to_zero_items(): void
    {
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response('<html><body><div class="card-item">restructured page</div></body></html>', 200),
        ]);

        // Pre-fix: exit SUCCESS, one info line, zero warnings — the exact
        // "vanishes" outcome #181's comment says must not happen.
        $this->artisan('crawl:news')
            ->expectsOutputToContain('Không phân tích được tin nào')
            ->assertFailed();

        $this->assertSame(0, News::count());
    }

    /**
     * Issue #299 (console-1): the #293 zero-parse guard was ported to
     * CrawlNews only. CrawlWantedList has the same shape — its $count lives
     * INSIDE the loop — so when truyna.bocongan.gov.vn drops or renames its
     * table element, filter('table tr') matches nothing, the loop never
     * runs, and the hourly run printed "Đã crawl xong , tổng cộng: 0 đối
     * tượng." + SUCCESS: the wanted list and the dashboard hotWanted tile
     * freeze on stale fugitives while the operator's only monitoring signal
     * (exit code) lies.
     */
    public function test_wanted_list_crawl_fails_loudly_when_the_table_parses_to_zero_rows(): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response('<html><body><div class="new-layout">no table here</div></body></html>', 200),
        ]);

        // Pre-fix: exit SUCCESS with "0 đối tượng", indistinguishable from
        // a healthy crawl — the exact failure mode #293 diagnosed for news.
        $this->artisan('crawl:wanted-list')
            ->expectsOutputToContain('Không phân tích được dòng nào')
            ->assertFailed();

        $this->assertSame(0, WantedPerson::count());
    }

    /**
     * Issue #299: the zero-NODES check alone still misses the column-
     * renumber case — rows are present but every one is skipped by the
     * <8-<td> shape guard (e.g. upstream merges cells), so $count stays 0
     * through the loop body's continue paths. A run that parsed nothing
     * from a non-empty page is just as silent a freeze.
     */
    public function test_wanted_list_crawl_fails_loudly_when_every_row_is_skipped(): void
    {
        Http::fake([
            'truyna.bocongan.gov.vn/*' => Http::response('<table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>', 200),
        ]);

        // Pre-fix: rows exist, all skipped, printed "0 đối tượng" + SUCCESS.
        $this->artisan('crawl:wanted-list')
            ->expectsOutputToContain('Không phân tích được dòng nào')
            ->assertFailed();

        $this->assertSame(0, WantedPerson::count());
    }

    /**
     * Issue #319: the #299 port's second half. CrawlNews guards zero NODES
     * (#293) but not zero PARSED: if VnExpress keeps .item-news but renames
     * the anchor inside it, every node takes the uncounted continue at the
     * titleNode check — $skipped (the #181 counter) never moves either, it
     * only starts after a title node exists — so the run printed
     * "Đã crawl xong 0 tin tức" + SUCCESS from a two-item page, the exact
     * silent freeze #293 was written to catch. Probed on pre-fix code:
     * nodes=2, both skipped uncounted, exit SUCCESS, zero warn lines.
     */
    public function test_news_crawl_fails_loudly_when_every_item_lacks_a_title_link(): void
    {
        Http::fake([
            'vnexpress.net/phap-luat' => Http::response(
                '<div class="item-news"><h3 class="new-title"><a href="/phap-luat/a-1.html">T1</a></h3></div>'
                .'<div class="item-news"><h3 class="new-title"><a href="/phap-luat/b-2.html">T2</a></h3></div>',
                200
            ),
        ]);

        // Post-loop $count === 0 with a non-empty listing: loud FAILURE, no
        // rows written, message distinct from the zero-NODES guard above
        // ("từ trang tin" vs "từ trang danh sách").
        $this->artisan('crawl:news')
            ->expectsOutputToContain('Không phân tích được tin nào từ trang tin')
            ->assertFailed();

        $this->assertSame(0, News::count());
    }
}
