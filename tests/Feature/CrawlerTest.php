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
}
