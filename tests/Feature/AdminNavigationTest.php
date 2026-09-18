<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #345: the admin nav block in layouts/navigation.blade.php had a
 * stray </li> with no opening <li>. An HTML parser does not silently fix
 * that — it rejects the enclosing <ul> as malformed, and neither the <ul>
 * nor its links render at all. So every admin in the app lost their top
 * navigation to Báo cáo and Bài viết, leaving the dashboard card buttons as
 * the only path. Probed live: the second ul rendered zero <li> children.
 *
 * This is invisible to every grep, every Blade lint and every route test —
 * the view renders 200 and the strings are all present in source. Only the
 * DOM a browser actually builds reveals it, so the tests below parse the
 * rendered HTML with DOMDocument rather than asserting on substrings.
 */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function navLinksFor(User $user): array
    {
        $html = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();

        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $text = trim(preg_replace('/\s+/', ' ', $a->textContent));
            if ($text !== '' && $a->hasAttribute('href')) {
                $links[$text] = $a->getAttribute('href');
            }
        }

        return $links;
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->forceFill(['isAdmin' => true])->save());
    }

    public function test_admin_sees_the_admin_links_in_their_navigation(): void
    {
        $links = $this->navLinksFor($this->admin());

        $this->assertSame(route('admin.alerts'), $links['Báo cáo'] ?? null);
        $this->assertSame(route('admin.experiences'), $links['Bài viết'] ?? null);
    }

    public function test_regular_user_sees_no_admin_navigation(): void
    {
        $links = $this->navLinksFor(User::factory()->create());

        $this->assertArrayNotHasKey('Báo cáo', $links);
        $this->assertArrayNotHasKey('Bài viết', $links);
    }

    public function test_admin_navigation_lands_in_a_well_formed_list(): void
    {
        // The pre-#345 markup was rejected by the parser, so the <ul> ended
        // up with zero <li> children even though the source had two. This
        // asserts the structure, not just the strings: a future stray tag
        // reproduces the whole defect and the text assertions alone would
        // still pass (the strings exist in the source the parser dropped).
        $html = $this->actingAs($this->admin())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><meta charset="utf-8">'.$html);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        // Find the ul whose text content carries the admin items.
        $adminUl = null;
        foreach ($xp->query('//ul[contains(@class,"nav-menu")]') as $ul) {
            if (str_contains($ul->textContent, 'Báo cáo')) {
                $adminUl = $ul;
            }
        }

        $this->assertNotNull($adminUl, 'the admin nav-menu must render at all');

        $li = 0;
        foreach ($adminUl->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'li') {
                $li++;
            }
        }

        $this->assertGreaterThanOrEqual(2, $li, 'admin nav-menu must contain real <li> children');
    }
}
