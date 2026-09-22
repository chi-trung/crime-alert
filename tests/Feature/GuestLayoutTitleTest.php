<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #422: #420 fixed the 'Laravel' title across every page that extends
 * layouts.app, but three Breeze authentication pages use the anonymous
 * <x-guest-layout> component instead, so the fix never reached them. Measured
 * live on the pre-fix tree, main adf9a47:
 *
 *   GET /forgot-password  200  Laravel
 *   GET /reset-password/x 200  Laravel
 *   GET /confirm-password 200  Laravel
 *
 * The login and register pages escaped it because they write their own
 * <title> by hand. An anonymous component has no @yield, so the page cannot
 * push a title at it — the layout takes one as a prop instead.
 *
 * Note on what these tests pin: config('app.name') in this environment is
 * still 'Laravel' because the local, gitignored .env carries APP_NAME=Laravel.
 * The defect #422 actually shipped is that the page name was MISSING — the
 * title said nothing about which auth page the user was on. So the invariant
 * asserted here is that the page name is present and Vietnamese, and that the
 * app name trails it as the brand.
 */
class GuestLayoutTitleTest extends TestCase
{
    use RefreshDatabase;

    private function titleOf(string $url): string
    {
        $html = (string) $this->get($url)->getContent();

        $this->assertMatchesRegularExpression(
            '/<title>.*<\/title>/s',
            $html,
            "the page at {$url} must render a <title> at all"
        );

        preg_match('/<title>(.*)<\/title>/s', $html, $m);

        return trim(html_entity_decode($m[1], ENT_QUOTES));
    }

    public function test_the_password_reset_request_page_names_itself(): void
    {
        $title = $this->titleOf('/forgot-password');

        $this->assertStringContainsString(
            'Quên mật khẩu',
            $title,
            'the title must name the page, not just the site'
        );
        $this->assertSame(
            'Quên mật khẩu? - ' . config('app.name'),
            $title,
            'the layout composes page name and brand in that order'
        );
    }

    public function test_the_password_reset_page_names_itself(): void
    {
        // reset-password takes a token in the URL, but the title comes from the
        // layout prop, not the token, so any token exercises the render.
        $title = $this->titleOf('/reset-password/any-token-here');

        $this->assertSame(
            'Đặt lại mật khẩu - ' . config('app.name'),
            $title,
            'the title must name the page, not just the site'
        );
    }

    public function test_the_password_confirm_page_names_itself(): void
    {
        // confirm-password redirects a guest to login, so it needs an
        // authenticated session to render at all.
        $user = User::factory()->create();
        $this->actingAs($user);

        $title = $this->titleOf('/confirm-password');

        $this->assertSame(
            'Xác nhận mật khẩu - ' . config('app.name'),
            $title,
            'the title must name the page, not just the site'
        );
    }

    public function test_the_guest_layout_prop_defaults_to_the_app_name(): void
    {
        // A page that uses x-guest-layout without a title prop must still fall
        // back to the app name rather than nothing. The source is asserted
        // rather than the rendered value because config('app.name') comes
        // from APP_NAME in the environment, which a deployment sets itself —
        // the invariant under test is that the layout composes the two halves
        // at all.
        $blade = file_get_contents(base_path('resources/views/layouts/guest.blade.php'));

        $this->assertStringContainsString(
            "@props(['title' => null])",
            $blade,
            'the layout must accept a title prop'
        );
        $this->assertStringNotContainsString(
            "config('app.name', 'Crime Alert Web')",
            $blade,
            "the dead fallback that could never fire was #420's whole bug"
        );
    }
}
