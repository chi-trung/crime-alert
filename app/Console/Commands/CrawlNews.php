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
        // Issue #293: #181's "a silent markup change surfaces as one warn
        // line" only covered href-less items — its counter lives INSIDE the
        // loop, so the likeliest change of all (the .item-news class renamed)
        // parsed to zero nodes, never entered the loop, and the run printed
        // "Đã crawl xong 0 tin tức" + SUCCESS while the feed froze
        // invisibly. Zero nodes is its own loud failure.
        if ($items->count() === 0) {
            $this->warn('Không phân tích được tin nào từ trang danh sách — nguồn có thể đã đổi cấu trúc.');

            return self::FAILURE;
        }
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
            // Issue #293: #100 truncated to the first 255 chars, but VnExpress
            // puts the uniqueness discriminator at the END of the URL (the
            // '-<id>.html' article number, '?zpage='/'&utm' params), so two
            // over-length articles sharing a 255-char prefix collapsed onto
            // one key of the UNIQUE link column — the #181 collapse shape
            // reopened through the truncation path. The column cannot hold the
            // full URL, so swap the lost tail for a digest of the WHOLE link:
            // distinct links get distinct keys, a re-crawl of the same link
            // gets the same key (updateOrCreate stays idempotent), and a
            // digest collision needs 2^-44, not a shared prefix. Under-length
            // links keep #100's byte-exact value untouched, and the result
            // stays 255 chars (same accepted mb-vs-byte trade as #100/#106).
            if (mb_strlen($link) > 255) {
                $link = mb_substr($link, 0, 243).'~'.substr(md5($link), 0, 11);
            } else {
                $link = mb_substr($link, 0, 255);
            }
            // Issue #325: description/image_url used to ride the updateOrCreate
            // payload unconditionally, with $desc/$img null when the listing
            // node carries no .description/<img>. A re-crawl of such a page
            // then WIPED the stored description/image_url to NULL — the same
            // loss shape #16 closed for published_at (listing carries no
            // timestamp, so it stays out of the payload). Absent-on-page is
            // not deleted-upstream; only a parsed value may overwrite. Fresh
            // rows still land NULL via the column defaults (both nullable),
            // and title/is_video always parse, so they stay unconditional.
            $update = [
                'title' => mb_substr($title, 0, 255),
                'is_video' => $isVideo,
            ];
            if ($desc !== null) {
                $update['description'] = $desc;
            }
            if ($img !== null) {
                $update['image_url'] = mb_substr($img, 0, 255);
            }
            News::updateOrCreate(
                ['link' => $link],
                $update
            );
            $count++;
        }
        if ($skipped > 0) {
            $this->warn("Bỏ qua {$skipped} tin không có đường dẫn (nguồn có thể đã đổi cấu trúc).");
        }
        // Issue #319: #299's second half, ported to this twin. The zero-NODES
        // guard above only catches the selector matching nothing; if upstream
        // keeps .item-news but renames the anchor inside it (.title-news a ->
        // something else), every node takes the UNCOUNTED continue at the
        // titleNode check — $count stays 0, $skipped stays 0 (that counter
        // only starts once an item HAS a title node), and the run printed
        // "Đã crawl xong 0 tin tức" + SUCCESS exactly like the #293 freeze it
        // was written to catch. Probed on the pre-fix code: nodes=2, both
        // skipped uncounted, exit SUCCESS with zero warn lines. A run that
        // parsed nothing from a non-empty page is a loud failure, same
        // doctrine as CrawlWantedList's post-loop guard.
        if ($count === 0) {
            $this->warn('Không phân tích được tin nào từ trang tin — nguồn có thể đã đổi cấu trúc.');

            return self::FAILURE;
        }
        $this->info("Đã crawl xong $count tin tức pháp luật từ VnExpress.");

        return self::SUCCESS;
    }
}
