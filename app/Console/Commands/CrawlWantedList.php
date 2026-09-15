<?php

namespace App\Console\Commands;

use App\Models\WantedPerson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class CrawlWantedList extends Command
{
    protected $signature = 'crawl:wanted-list';

    protected $description = 'Crawl danh sách truy nã từ truyna.bocongan.gov.vn';

    public function handle(): int
    {
        $url = 'https://truyna.bocongan.gov.vn/';
        // Chỉ GET trang chủ
        try {
            $response = Http::timeout(30)->connectTimeout(10)->get($url);
        } catch (\Throwable $e) {
            $this->warn('Không tải được trang truy nã ('.$e->getMessage().'), bỏ lượt crawl này.');

            return self::FAILURE;
        }
        if ($response->failed()) {
            $this->warn('Không tải được trang truy nã (HTTP '.$response->status().'), bỏ lượt crawl này.');

            return self::FAILURE;
        }

        $crawler = new Crawler($response->body());
        $rows = $crawler->filter('table tr');
        // Issue #299 (console-1): the #293 zero-parse guard, ported to this
        // twin. $count lives INSIDE the loop, so if upstream drops or
        // renames its table element the filter matches nothing, the loop
        // never runs, and the hourly run printed "0 đối tượng" + SUCCESS
        // while the wanted list froze invisibly. Same for the all-skipped
        // case (rows present but none survive the <8-<td>/regex guards, e.g.
        // a column renumber), checked after the loop.
        if ($rows->count() === 0) {
            $this->warn('Không phân tích được dòng nào từ bảng truy nã — nguồn có thể đã đổi cấu trúc.');

            return self::FAILURE;
        }
        $count = 0;
        // Duyệt từ cuối lên đầu để đảo ngược thứ tự
        for ($i = $rows->count() - 1; $i >= 0; $i--) {
            $row = $rows->eq($i);
            $cols = $row->filter('td');
            if ($cols->count() < 8) {
                continue;
            }
            $name = trim($cols->eq(1)->text());
            $birthYear = trim($cols->eq(2)->text());
            if (is_numeric($name) || $name === '' || $name === 'Họ tên' || ! preg_match('/^(19|20)\d{2}$/', $birthYear)) {
                continue;
            }
            // Issue #106: same class as #100 (News, fixed in #101) — these
            // cells come off an untrusted third-party DOM but land in
            // VARCHAR(255) columns, so one over-length cell 500s every
            // scheduled run on MySQL (SQLite ignores the limit, so CI never
            // caught it). Truncate on ingest, lookup keys included — the
            // updateOrCreate query itself would 1406 before any insert.
            // birth_year is regex-pinned to four digits, so it needs none.
            // Same accepted tradeoff as #100/#101: mb_substr counts
            // characters while utf8mb4 measures bytes.
            // Issue #293: (name, birth_year, address) is NOT a person
            // identity — the site's own listings carry same-named, same-age
            // relatives at one household address (and truncation can equalise
            // distinct names), with no UNIQUE index to catch the merge. The
            // second row overwrote the first: a fugitive vanished from
            // /wanted-list and the dashboard hotWanted tile, and the
            // surviving row's crime/decision described only one of them.
            // The decision number is the per-record discriminator, so it
            // belongs IN the key, alongside the #106 truncation.
            $decision = mb_substr(trim($cols->eq(6)->text()), 0, 255);
            WantedPerson::updateOrCreate([
                'name' => mb_substr($name, 0, 255),
                'birth_year' => $birthYear,
                'address' => mb_substr(trim($cols->eq(3)->text()), 0, 255),
                'decision' => $decision,
            ], [
                'parents' => mb_substr(trim($cols->eq(4)->text()), 0, 255),
                'crime' => mb_substr(trim($cols->eq(5)->text()), 0, 255),
                'decision' => $decision,
                'agency' => mb_substr(trim($cols->eq(7)->text()), 0, 255),
            ]);
            $count++;
        }
        if ($count === 0) {
            // Rows were present but every one was skipped by the shape
            // guards — a column renumber, not an empty feed. Loud failure
            // for the same silent-freeze reason as the zero-rows guard.
            $this->warn('Không phân tích được dòng nào từ bảng truy nã — nguồn có thể đã đổi cấu trúc.');

            return self::FAILURE;
        }
        $this->info("Đã crawl xong , tổng cộng: $count đối tượng.");

        return self::SUCCESS;
    }
}
