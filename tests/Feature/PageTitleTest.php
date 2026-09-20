<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #420: every page in the app shipped the literal title "Laravel".
 * Measured live on the pre-fix tree, authenticated through the test harness:
 *
 *   GET /dashboard      200  Laravel
 *   GET /alerts         200  Laravel
 *   GET /alerts/create  200  Laravel
 *   GET /alerts/map     200  Laravel
 *   GET /alerts/{id}    200  Laravel
 *   GET /experiences    200  Laravel
 *   GET /notifications  200  Laravel
 *   GET /profile        200  Laravel
 *   GET /news           200  Laravel
 *   GET /wanted-list    200  Laravel
 *   GET /support        200  Laravel
 *
 * Only the homepage escaped it, because welcome.blade.php writes its own
 * <title> by hand. The cause was not a missing config value: config/app.php
 * fell back to 'Laravel', and the old layout expression
 * `config('app.name', 'Crime Alert Web')` could never reach its fallback
 * because config('app.name') is always defined. The fix has two halves — a
 * fallback that no longer names a framework, and a @section('title') hook in
 * the layout that each page fills with its own name.
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    private function titleOf(string $url, ?User $user = null): string
    {
        $request = $user ? $this->actingAs($user)->get($url) : $this->get($url);
        $html = (string) $request->getContent();

        $this->assertMatchesRegularExpression(
            '/<title>.*<\/title>/s',
            $html,
            "the page at {$url} must render a <title> at all"
        );

        preg_match('/<title>(.*)<\/title>/s', $html, $m);

        return trim($m[1]);
    }

    public function test_the_layout_default_no_longer_names_a_framework(): void
    {
        // The config fallback used to be 'Laravel', so a deployment that never
        // set APP_NAME branded every page with a framework's name. Forcing the
        // env value out from under the app proves the fallback changed.
        //
        // The probe has to run against a page that extends layouts.app: the
        // homepage does not, it writes its own <title> by hand.
        config(['app.name' => null]);

        $user = User::factory()->create();
        $title = $this->titleOf('/dashboard', $user);

        $this->assertStringNotContainsString('Laravel', $title);
        $this->assertStringContainsString(
            'Crime Alert Web',
            $title,
            'with no APP_NAME the layout must fall back to the product name'
        );
    }

    public function test_authenticated_pages_name_themselves_and_carry_the_brand(): void
    {
        $user = User::factory()->create();

        $pages = [
            '/dashboard' => 'Bảng điều khiển',
            '/alerts' => 'Cảnh báo tội phạm cộng đồng',
            '/alerts/create' => 'Đăng cảnh báo tội phạm',
            '/alerts/map' => 'Bản đồ cảnh báo tội phạm',
            '/experiences' => 'Chia sẻ kinh nghiệm',
            '/notifications' => 'Tất cả thông báo',
            '/profile' => 'Hồ sơ cá nhân',
            '/news' => 'Tin tức',
            '/wanted-list' => 'Danh sách đối tượng truy nã',
            '/support' => 'Danh sách yêu cầu hỗ trợ',
        ];

        foreach ($pages as $url => $expected) {
            $title = $this->titleOf($url, $user);

            $this->assertStringNotContainsString(
                'Laravel',
                $title,
                "{$url}: a page title must not name a framework"
            );
            $this->assertStringContainsString(
                $expected,
                $title,
                "{$url}: the title must name the page the user is on"
            );
            $this->assertStringContainsString(
                'Crime Alert Web',
                $title,
                "{$url}: the title must keep the product name so tabs and bookmarks identify the site"
            );
        }
    }

    public function test_an_alert_detail_page_titles_the_alert_not_the_section(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $user->id,
            'title' => 'Gần đây tôi thấy kẻ khả nghi ở công viên',
            'description' => 'body',
            'status' => 'approved',
            'type' => 'theft',
        ]);

        // A detail page is the one place a generic title actively misleads: the
        // tab should say which alert it is.
        $title = $this->titleOf("/alerts/{$alert->id}", $user);

        $this->assertStringContainsString(
            $alert->title,
            $title,
            'the alert title is the thing the page is about'
        );
        $this->assertStringNotContainsString('Laravel', $title);
    }

    public function test_the_config_default_is_not_the_framework_name(): void
    {
        // Pinned at the source so a future 'Laravel' default in app.php is
        // caught directly. This is deliberately a source assertion rather than
        // a config('app.name') call: the resolved value comes from APP_NAME in
        // the environment, which can legitimately name a specific deployment,
        // so only the built-in default is the invariant under test.
        $appConfig = file_get_contents(config_path('app.php'));

        $this->assertMatchesRegularExpression(
            "/'name' => env\('APP_NAME', 'Crime Alert Web'\)/",
            $appConfig,
            'the built-in default must be the product name, never the framework'
        );
    }
}
