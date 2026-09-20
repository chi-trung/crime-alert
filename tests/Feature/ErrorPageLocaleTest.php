<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #418: the project had no resources/views/errors/ and no
 * Route::fallback, so every 404/403/500 rendered the framework's stock
 * page. Measured live on the pre-fix tree: 404, 6659 bytes, no app chrome,
 * <title>Not Found</title> and <html lang="en"> — English copy with no way
 * back except the browser's back button, on a Vietnamese app.
 *
 * The pages now extend layouts.app, so the nav, the footer and the dynamic
 * lang tag all come along and the layout's own @auth branch handles the
 * login-link-versus-profile-menu split.
 */
class ErrorPageLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_alert_renders_the_app_layout_in_vietnamese(): void
    {
        $user = User::factory()->create();

        $r = $this->actingAs($user)->get('/alerts/9999999');

        $r->assertStatus(404);
        $html = $r->getContent();

        $this->assertStringContainsString(
            'Không tìm thấy trang',
            $html,
            'a 404 must speak the app language, not "Not Found"'
        );
        $this->assertStringContainsString(
            'Hệ thống cảnh báo tội phạm',
            $html,
            'a 404 must keep the app footer so the user is not stranded'
        );
        $this->assertMatchesRegularExpression(
            '/<html lang="vi">/',
            $html,
            'the lang tag must stay vi — the app is Vietnamese, #331'
        );
    }

    public function test_a_missing_route_renders_the_same_page(): void
    {
        $user = User::factory()->create();

        $r = $this->actingAs($user)->get('/totally-fake-route-xyz');

        $r->assertStatus(404);
        $this->assertStringContainsString(
            'Không tìm thấy trang',
            $r->getContent(),
            'an unknown route must not fall back to the framework default'
        );
    }

    public function test_the_404_offers_a_way_home(): void
    {
        $user = User::factory()->create();

        // The whole point: the stock page had no navigation at all, so the
        // browser back button was the only exit. url('/') rather than a named
        // route because the homepage closure is unnamed.
        $r = $this->actingAs($user)->get('/alerts/9999999');
        $r->assertStatus(404);
        $this->assertStringContainsString(
            url('/'),
            $r->getContent(),
            'a stranded user needs a visible route back to the homepage'
        );
    }

    public function test_the_403_page_branches_on_authentication(): void
    {
        // The 403 page must not assume the user is signed in: a guest hitting
        // a gated resource needs a login link, not a dashboard link. Every
        // 403-producing route sits behind the auth middleware, so a guest is
        // redirected before the abort fires — the branch is asserted on the
        // template itself, which is what the layout's @auth renders.
        $blade = file_get_contents(base_path('resources/views/errors/403.blade.php'));

        $this->assertStringContainsString(
            "route('login')",
            $blade,
            'the guest branch must offer a login link'
        );
        $this->assertStringContainsString(
            "route('dashboard')",
            $blade,
            'the signed-in branch must offer the dashboard instead'
        );
    }
}
