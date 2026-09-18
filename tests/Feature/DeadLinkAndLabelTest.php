<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #345: the companion UI defects shipped with the navigation fix.
 * Each is a dead or misleading affordance — a link that navigates nowhere,
 * a label that describes nothing, or a tab-target that hands the opener
 * handle to an untrusted destination. None of them throws, so no existing
 * test could catch them.
 */
class DeadLinkAndLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_external_anchor_opens_without_rel_noopener(): void
    {
        // Every target="_blank" passes the new tab a window.opener handle,
        // which a malicious destination can redirect the CRIME-ALERT tab to a
        // lookalike. rel="noopener" breaks the handle; the news links are the
        // live concern since their href comes from the crawler, not from us.
        $views = [
            '/news',
            '/wanted-list',
        ];

        foreach ($views as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();

            $dom = new \DOMDocument;
            libxml_use_internal_errors(true);
            $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
            libxml_clear_errors();

            foreach ($dom->getElementsByTagName('a') as $a) {
                if ($a->getAttribute('target') === '_blank') {
                    $this->assertSame(
                        'noopener',
                        $a->getAttribute('rel'),
                        $uri.': anchor to '.$a->getAttribute('href').' opens a new tab without rel="noopener".',
                    );
                }
            }
        }
    }

    public function test_show_pages_keep_their_share_and_map_links_safe(): void
    {
        // alerts/show and experiences/show carry the share buttons plus the
        // Google Maps link — the map href interpolates coordinates, so the
        // noopener on that one is the load-bearing case.
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'T',
            'description' => 'd',
            'status' => 'approved',
            'latitude' => '10.762622',
            'longitude' => '106.660172',
        ]);

        $html = $this->actingAs($user)
            ->get('/alerts/'.$alert->id)
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();

        $blanks = 0;
        foreach ($dom->getElementsByTagName('a') as $a) {
            if ($a->getAttribute('target') === '_blank') {
                $blanks++;
                $this->assertSame('noopener', $a->getAttribute('rel'));
            }
        }

        $this->assertGreaterThan(0, $blanks, 'this page must still have its new-tab links');
    }

    public function test_register_page_does_not_link_terms_to_nowhere(): void
    {
        // The terms/privacy "links" were href="#" — clicking scrolled the page
        // to the top and showed nothing. They were the only references to
        // either document in the app, so they were not links, they were text
        // styled as links. Demoted to plain text; no terms document exists.
        $html = $this->get('/register')->assertOk()->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();

        foreach ($dom->getElementsByTagName('a') as $a) {
            $this->assertNotSame(
                '#',
                $a->getAttribute('href'),
                'no anchor may point at "#": '.$a->textContent,
            );
        }

        // The text still has to carry the promise — an agreement checkbox
        // with no mention of what is being agreed is worse than a dead link.
        $this->assertStringContainsString('Điều khoản dịch vụ', $html);
        $this->assertStringContainsString('Chính sách bảo mật', $html);
    }

    public function test_dashboard_chart_menu_has_no_dead_items(): void
    {
        // The ellipsis menu on the admin's monthly chart had two href="#"
        // items and no handler anywhere. "Xuất báo cáo" had no export behind
        // it at all and is gone; "Xem chi tiết" now opens the alert index.
        // Scoped to the chart card's own dropdown menu: the bell and profile
        // icons in the nav legitimately hold href="#" and are driven by JS
        // with preventDefault, so a whole-page sweep would flag them false
        // positives.
        $html = $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Xuất báo cáo', $html);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        $menu = null;
        foreach ($xp->query('//ul[contains(@class,"dropdown-menu")]') as $m) {
            if (str_contains($m->textContent, 'Xem chi tiết')) {
                $menu = $m;
            }
        }

        $this->assertNotNull($menu, 'the chart detail menu must still render');

        foreach ($menu->getElementsByTagName('a') as $a) {
            $this->assertNotSame('#', $a->getAttribute('href'));
            $this->assertNotEmpty($a->getAttribute('href'));
        }
    }

    public function test_dashboard_timestamp_is_labeled_as_the_load_time(): void
    {
        // The header read "Cập nhật lần cuối" (last updated) over now() — the
        // current server time on every render. A reload produced a NEW now(),
        // so the label described nothing it could ever be true about. It is
        // the page-load time and is now named as such.
        $html = $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Cập nhật lần cuối', $html);
        $this->assertStringContainsString('Thời gian tải trang', $html);
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['isAdmin' => true])->save());
    }
}
