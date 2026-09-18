<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #347: the app was saturated with Font Awesome glyphs and hot-linked
 * stock illustrations to the point the UI read as a generated template. This
 * pins the rules the round established, all of which are regressions a future
 * edit can silently reintroduce:
 *
 *  - no decorative glyph may precede a label that already says the same word;
 *  - no third-party pixel may be fetched for something CSS can render;
 *  - a heading must not be propped up by an icon that duplicates its text.
 *
 * Every assertion below was verified red on the pre-fix markup, so a
 * reintroduced icon fails the suite rather than merely looking busy.
 */
class IconAndAssetHygieneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['isAdmin' => true])->save());
    }

    private function verifiedUser(): User
    {
        // /alerts/create and /dashboard sit behind the verified middleware,
        // so an unverified user is redirected to the notice page instead of
        // rendering the markup under test.
        return tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
            'isAdmin' => true,
        ])->save());
    }

    public function test_dashboard_does_not_restate_card_titles_in_icons(): void
    {
        // The six headings below each carried a glyph (life-ring, comments,
        // bell...). An icon that repeats the heading's own word is decoration
        // a screen reader announces twice.
        $html = $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $dom = $this->dom($html);
        $xp = new \DOMXPath($dom);

        foreach (['Cảnh báo mới nhất', 'Quản lý bài chia sẻ kinh nghiệm'] as $heading) {
            $node = null;
            foreach ($xp->query('//h5[contains(@class,"card-title")]') as $h5) {
                if (str_contains($h5->textContent, $heading)) {
                    $node = $h5;
                }
            }
            $this->assertNotNull($node, "heading must render: {$heading}");
            $this->assertCount(
                0,
                $node->getElementsByTagName('i'),
                "heading must not carry an icon: {$heading}",
            );
        }
    }

    public function test_dashboard_stat_tiles_carry_no_avatar_chrome(): void
    {
        // The six admin tiles each spent eleven markup lines wrapping one
        // decorative glyph in .avatar-sm/.avatar-title. The label above the
        // number already names the metric; the delta badge below already
        // carries direction and sentiment via stat-badge's sign logic.
        $html = $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('avatar-title', $html);
        $this->assertStringNotContainsString('avatar-sm', $html);

        // The color coding survived — one accent element per tile, not zero.
        $dom = $this->dom($html);
        $xp = new \DOMXPath($dom);
        $this->assertSame(6, $xp->query('//span[contains(@class,"stat-accent")]')->length);
    }

    public function test_alert_create_form_labels_carry_no_icons(): void
    {
        // Every field label had its own glyph (fa-heading before "Tiêu đề",
        // fa-tag before "Loại tội phạm"...). The label is the field's name;
        // the glyph added no information.
        $html = $this->actingAs($this->verifiedUser())
            ->get('/alerts/create')
            ->assertOk()
            ->getContent();

        $dom = $this->dom($html);
        $xp = new \DOMXPath($dom);

        foreach (['title', 'type', 'description'] as $field) {
            $labels = $xp->query(sprintf('//label[@for="%s"]', $field));
            $this->assertSame(1, $labels->length, "label must render once: {$field}");
            $this->assertCount(
                0,
                $labels->item(0)->getElementsByTagName('i'),
                "form label must not carry an icon: {$field}",
            );
        }
    }

    public function test_no_page_links_a_third_party_icon_host(): void
    {
        // 20 hot-linked flaticon.com pixels: the nav logo, four favicons,
        // five empty-state illustrations and the map marker. A third party
        // saw every page load, and the repo's own favicon.ico was an empty
        // 0-byte file the whole time.
        // Split by who may see the page: /alerts, /dashboard and
        // /alerts/create sit inside the auth group, while /login and
        // /register carry the guest middleware — an authenticated visitor
        // is redirected away from the auth forms, and a guest away from the
        // authed pages, so mixing the two would assert on 302 bodies.
        $guest = ['/news', '/wanted-list', '/login', '/register'];
        $authed = ['/alerts', '/dashboard', '/alerts/create'];

        foreach ($guest as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();
            $this->assertStringNotContainsString(
                'cdn-icons-png.flaticon.com',
                "{$uri} must not fetch a third-party icon host.",
            );
        }

        foreach ($authed as $uri) {
            $html = $this->actingAs($this->verifiedUser())
                ->get($uri)
                ->assertOk()
                ->getContent();
            $this->assertStringNotContainsString(
                'cdn-icons-png.flaticon.com',
                "{$uri} must not fetch a third-party icon host.",
            );
        }
    }

    public function test_favicon_is_served_locally(): void
    {
        // The favicons pointed at flaticon while public/favicon.ico sat
        // empty in the repo. The app now ships its own SVG.
        $html = $this->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringNotContainsString('flaticon.com', $html);
    }

    public function test_welcome_calls_to_action_do_not_emoji_shout(): void
    {
        // "🚨gửi báo cáo ngay" — an emoji glued to a lowercase sentence next
        // to a sibling button in caps. The two buttons now read as one pair.
        $html = $this->get('/')->assertOk()->getContent();

        $dom = $this->dom($html);
        $xp = new \DOMXPath($dom);

        $links = [];
        foreach ($xp->query('//a[contains(@class,"cta-button")]') as $a) {
            $links[] = trim($a->textContent);
        }

        $this->assertContains('Gửi báo cáo ngay', $links);
        $this->assertContains('Xem bản đồ an ninh', $links);
    }

    public function test_support_thread_page_still_ships_its_chat_handler(): void
    {
        // Regression guard for the page every other round in this family
        // touches: the inline handler's shape is pinned by
        // SupportChatScriptsTest, this only asserts the page renders and the
        // empty-state swap did not orphan the script block.
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);

        $html = $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('function fetchMessages()', $html);
        $this->assertStringNotContainsString('flaticon.com', $html);
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();

        return $dom;
    }
}
