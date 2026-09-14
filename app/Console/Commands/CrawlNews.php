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
        $items = $crawler->filter('.item-news');
        $count = 0;
        // Issue #181: count href-less items so a silent upstream markup
        // change surfaces as one warn line per run instead of vanishing.
        $skipped = 0;
        // Issue #236: iterate bottom-up, mirroring CrawlWantedList's
        // documented "Duyệt từ cuối lên đầu để đảo ngược thứ tự" convention
        // (Symfony 7's Crawler has no reverse(), so the for/eq loop is the
        // twin's exact shape). Both feed readers sort
        // orderByDesc('published_at')->orderByDesc('id'), and a crawled row
        // always has published_at NULL by #16's contract — NULL ties under
        // DESC on both MySQL and SQLite, so the id tiebreak alone orders the
        // whole crawled block. Forward document order (listing is newest-
        // first) gave the OLDEST article the highest id, i.e. the newest
        // crawled news sat last on /news and in the dashboard's latestNews
        // tile. Reverse iteration inserts newest last, so newest gets the
        // highest id and the feed finally reads newest-first.
        for ($i = $items->count() - 1; $i >= 0; $i--) {
            $node = $items->eq($i);
            $titleNode = $node->filter('.title-news a');
            if (! $titleNode->count()) {
                continue;
            }
            $title = trim($titleNode->text());
            $link = $titleNode->attr('href');
            // Issue #181: Crawler::attr() returns null when the anchor has no
            // href. Passing that to the strpos/mb_substr below only raises
            // E_DEPRECATED (routed to the null deprecations channel —
            // invisible), then strpos(null,'http') === false makes the code
            // "absolutize" the null into the literal base domain, and every
            // href-less article on the page collapses into ONE fake row keyed
            // link='https://vnexpress.net' on the UNIQUE column, each
            // overwriting the last, with the crawl still reporting SUCCESS.
            // Skip such items outright instead.
            if (! is_string($link) || trim($link) === '') {
                $skipped++;

                continue;
            }
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
            // Issue #100: title/link/image_url are VARCHAR(255) but come off
            // an untrusted third-party DOM — one over-length string 500s
            // every scheduled run on MySQL (SQLite ignores the limit, so CI
            // never caught it; same dialect trap as #37/#39). Truncate on
            // ingest. mb_substr counts characters while utf8mb4 measures
            // bytes, so a pathological all-4-byte title could still exceed
            // 255 bytes — accepted: Vietnamese text averages ~2 bytes/char
            // and the alternative (byte-cutting mid-character) corrupts.
            $link = mb_substr($link, 0, 255);
            News::updateOrCreate(
                ['link' => $link],
                [
                    'title' => mb_substr($title, 0, 255),
                    'description' => $desc,
                    'image_url' => $img === null ? null : mb_substr($img, 0, 255),
                    'is_video' => $isVideo,
                ]
            );
            $count++;
        }
        if ($skipped > 0) {
            $this->warn("Bỏ qua {$skipped} tin không có đường dẫn (nguồn có thể đã đổi cấu trúc).");
        }
        $this->info("Đã crawl xong $count tin tức pháp luật từ VnExpress.");

        return self::SUCCESS;
    }
}
