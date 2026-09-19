<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #376: the news card had three image branches and all three were
 * broken. An item with no image shipped a literal <img src=""> — an empty src
 * resolves against the page URL, so the browser rendered a broken-image glyph,
 * not a placeholder, and the img-default-news class hung off it matched no rule
 * in any CSS file. A video item with no image rendered an empty black box, and
 * the video badge rendered as an empty span — no glyph, no text, no label.
 *
 * These branches are reachable, not dead: crawl:news always writes is_video and
 * only writes image_url when it actually parsed one, so any item missing an
 * image (and every video item) lands in a broken branch.
 */
class NewsCardImageTest extends TestCase
{
    use RefreshDatabase;

    private function renderIndex(): string
    {
        return $this->get(route('news.index'))->assertOk()->getContent();
    }

    private function makeNews(array $overrides): News
    {
        // CrawlNews::handle() writes rows this way: title/link/is_video always,
        // image_url only when a node parsed.
        return News::create(array_merge([
            'title' => 'Tin thử nghiệm',
            'link' => 'https://vnexpress.net/example-'.uniqid(),
            'description' => 'Mô tả',
            'published_at' => now(),
            'is_video' => false,
        ], $overrides));
    }

    public function test_an_item_without_an_image_does_not_ship_an_empty_src(): void
    {
        $this->makeNews(['image_url' => null]);
        $html = $this->renderIndex();

        // The whole point: an <img> whose src resolves to the page itself is a
        // broken image, not a placeholder.
        $this->assertStringNotContainsString(
            '<img src=""',
            $html,
            'a news item without an image must not ship an img with an empty src'
        );
        $this->assertStringNotContainsString(
            'img-default-news',
            $html,
            'the placeholder class that matched no CSS rule must be gone'
        );
    }

    public function test_an_item_without_an_image_gets_a_real_placeholder(): void
    {
        $item = $this->makeNews(['image_url' => null]);
        $html = $this->renderIndex();

        $this->assertStringContainsString('class="news-thumb-ph"', $html);
        $this->assertStringContainsString(
            'aria-label="'.e($item->title).'"',
            $html,
            'the placeholder must carry the title so a screen reader is not handed decoration'
        );
    }

    public function test_an_item_with_an_image_still_renders_it(): void
    {
        $item = $this->makeNews(['image_url' => 'https://example.com/a.jpg']);
        $html = $this->renderIndex();

        // Positive control: the fix must not take the real image with it.
        // Blade puts the src on its own line, so match the attribute, not the
        // full tag.
        $this->assertStringContainsString('src="'.e($item->image_url).'"', $html);
        $this->assertStringContainsString('alt="'.e($item->title).'"', $html);
        $this->assertStringContainsString('class="card-img-top"', $html);
        // The placeholder rule ships in the page's own <style> for every item,
        // so scope this negative check to the rendered markup, not the CSS.
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*news-thumb-ph/',
            $html,
            'an item that has an image must render the image, not the placeholder'
        );
    }

    public function test_the_video_badge_is_not_empty(): void
    {
        $this->makeNews(['is_video' => true, 'image_url' => null]);
        $html = $this->renderIndex();

        // The badge span used to ship with whitespace only. A play glyph is
        // what made it a badge, so assert the shape it now carries.
        $this->assertStringContainsString(
            '<span class="position-absolute top-50 start-50 translate-middle"',
            $html
        );
        $this->assertStringContainsString('<circle cx="12" cy="12" r="11"', $html);
        $this->assertStringContainsString('<path d="M10 8l6 4-6 4z" fill="#fff"></path>', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_a_video_item_with_an_image_keeps_both(): void
    {
        // This used to be the only branch that worked at all; it must still
        // render the image and now the badge too.
        $item = $this->makeNews([
            'is_video' => true,
            'image_url' => 'https://example.com/b.jpg',
        ]);
        $html = $this->renderIndex();

        $this->assertStringContainsString('src="'.e($item->image_url).'"', $html);
        $this->assertStringContainsString('<circle cx="12" cy="12" r="11"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*news-thumb-ph/',
            $html,
            'an item that has an image must render the image, not the placeholder'
        );
    }

    public function test_the_placeholder_style_is_defined_on_the_page(): void
    {
        // /news loads no page stylesheet, so the rule has to ship inline or the
        // placeholder is an unstyled div — the same trap img-default-news set.
        $this->makeNews(['image_url' => null]);
        $html = $this->renderIndex();

        $this->assertStringContainsString('.news-thumb-ph {', $html);
        $this->assertStringContainsString('repeating-linear-gradient', $html);
    }
}
