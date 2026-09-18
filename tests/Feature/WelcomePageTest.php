<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #341: the landing page used to ship three navigation defects.
 * Two feature cards pointed at the wrong page ('Hỗ trợ trực tuyến'
 * linked /notifications, not the support form; 'Chatbot AI' linked
 * /dashboard with no hint that the chatbot is a floating widget), and
 * the header showed 'Đăng nhập'/'Đăng ký' to users who were already
 * signed in. These pin all three.
 */
class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_auth_links_and_login_hints(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        // Guests get the login hint on the auth-gated feature cards.
        $response->assertSee('Cần đăng nhập', false);
    }

    public function test_authenticated_user_sees_dashboard_instead_of_auth_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee(route('dashboard'), false);
        $response->assertDontSee(route('login'), false);
        $response->assertDontSee(route('register'), false);
        // Signed-in users see no login hint: they can already open the cards.
        $response->assertDontSee('Cần đăng nhập', false);
    }

    /**
     * @dataProvider featureCards
     */
    public function test_feature_cards_point_at_the_page_they_describe(string $title, string $routeName): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee($title, false);
        $response->assertSee(route($routeName), false);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function featureCards(): array
    {
        return [
            'report' => ['Báo cáo nhanh', 'alerts.create'],
            'map' => ['Bản đồ an ninh', 'alerts.map'],
            'community' => ['Cộng đồng kết nối', 'experiences.create'],
            // #341: this card used to link /notifications.
            'support' => ['Hỗ trợ trực tuyến', 'support.create'],
            // #341: the chatbot is a floating widget, not its own page.
            'chatbot' => ['Chatbot AI', 'dashboard'],
            'notifications' => ['Thông báo', 'notifications.index'],
        ];
    }
}
