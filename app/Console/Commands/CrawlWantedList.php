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
            WantedPerson::updateOrCreate([
                'name' => mb_substr($name, 0, 255),
                'birth_year' => $birthYear,
                'address' => mb_substr(trim($cols->eq(3)->text()), 0, 255),
            ], [
                'parents' => mb_substr(trim($cols->eq(4)->text()), 0, 255),
                'crime' => mb_substr(trim($cols->eq(5)->text()), 0, 255),
                'decision' => mb_substr(trim($cols->eq(6)->text()), 0, 255),
                'agency' => mb_substr(trim($cols->eq(7)->text()), 0, 255),
            ]);
            $count++;
        }
        $this->info("Đã crawl xong , tổng cộng: $count đối tượng.");

        return self::SUCCESS;
    }
}
