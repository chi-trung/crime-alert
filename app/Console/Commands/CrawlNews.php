<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\News;
use Carbon\Carbon;

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
    public function handle()
    {
        $client = new Client(['timeout' => 20]);
        $url = 'https://vnexpress.net/phap-luat';
        $html = $client->get($url)->getBody()->getContents();
        $crawler = new Crawler($html);
        $count = 0;
        $crawler->filter('.item-news')->each(function ($node) use (&$count) {
            $titleNode = $node->filter('.title-news a');
            if (!$titleNode->count()) return;
            $title = trim($titleNode->text());
            $link = $titleNode->attr('href');
            if (strpos($link, 'http') !== 0) {
                $link = 'https://vnexpress.net' . $link;
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
            News::updateOrCreate([
                'link' => $link,
            ], [
                'title' => $title,
                'description' => $desc,
                'image_url' => $img,
                'published_at' => null,
                'is_video' => $isVideo,
            ]);
            $count++;
        });
        $this->info("Đã crawl xong $count tin tức pháp luật từ VnExpress.");
    }
}
