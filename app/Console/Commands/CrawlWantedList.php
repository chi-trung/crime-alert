<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\WantedPerson;

class CrawlWantedList extends Command
{
    protected $signature = 'crawl:wanted-list';
    protected $description = 'Crawl đúng STT 1-50 từ trang chủ truyna.bocongan.gov.vn';

    public function handle()
    {
        $client = new Client(['cookies' => true]);
        $url = 'https://truyna.bocongan.gov.vn/';
        // Chỉ GET trang chủ
        $html = $client->get($url)->getBody()->getContents();
        $crawler = new Crawler($html);
        $rows = $crawler->filter('table tr');
        $count = 0;
        // Duyệt từ cuối lên đầu để đảo ngược thứ tự
        for ($i = $rows->count() - 1; $i >= 0; $i--) {
            $row = $rows->eq($i);
            $cols = $row->filter('td');
            if ($cols->count() < 8) continue;
            $name = trim($cols->eq(1)->text());
            $birthYear = trim($cols->eq(2)->text());
            if (is_numeric($name) || $name === '' || $name === 'Họ tên' || !preg_match('/^(19|20)\\d{2}$/', $birthYear)) continue;
            WantedPerson::updateOrCreate([
                'name' => $name,
                'birth_year' => $birthYear,
                'address' => trim($cols->eq(3)->text()),
            ], [
                'parents' => trim($cols->eq(4)->text()),
                'crime' => trim($cols->eq(5)->text()),
                'decision' => trim($cols->eq(6)->text()),
                'agency' => trim($cols->eq(7)->text()),
            ]);
            $count++;
        }
        $this->info("Đã crawl xong , tổng cộng: $count đối tượng.");
    }
} 