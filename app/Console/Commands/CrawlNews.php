<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class CrawlNews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl tin tức pháp luật từ VnExpress';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = 'https://vnexpress.net/phap-luat';

        try {
            $response = Http::timeout(20)->connectTimeout(10)->get($url);
        } catch (\Throwable $e) {
            $this->warn('Không tải được trang tin tức ('.$e->getMessage().'), bỏ lượt crawl này.');

            return self::FAILURE;
        }
        if ($response->failed()) {
            $this->warn('Không tải được trang tin tức (HTTP '.$response->status().'), bỏ lượt crawl này.');

            return self::FAILURE;
        }

        $crawler = new Crawler($response->body());
        $count = 0;
        $crawler->filter('.item-news')->each(function ($node) use (&$count) {
            $titleNode = $node->filter('.title-news a');
            if (! $titleNode->count()) {
                return;
            }
            $title = trim($titleNode->text());
            $link = $titleNode->attr('href');
            if (strpos($link, 'http') !== 0) {
                $link = 'https://vnexpress.net'.$link;
            }
            $desc = $node->filter('.description')->count() ? trim($node->filter('.description')->text()) : null;
            $img = null;
            if ($node->filter('img')->count()) {
                $imgNode = $node->filter('img')->first();
                $img = $imgNode->attr('data-src') ?? $imgNode->attr('data-original') ?? $imgNode->attr('src');
            }
            $isVideo = false;
            if (
                ($node->filter('video')->count()) ||
                (strpos($link, '/video/') !== false)
            ) {
                $isVideo = true;
            }
            // published_at is intentionally NOT part of the update payload:
            // the listing carries no timestamp, and re-writing null here on
            // every crawl wiped values set manually/elsewhere (issue #16).
            News::updateOrCreate(
                ['link' => $link],
                [
                    'title' => $title,
                    'description' => $desc,
                    'image_url' => $img,
                    'is_video' => $isVideo,
                ]
            );
            $count++;
        });
        $this->info("Đã crawl xong $count tin tức pháp luật từ VnExpress.");

        return self::SUCCESS;
    }
}
