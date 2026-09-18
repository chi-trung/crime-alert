<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression net for the class of bug that shipped a 500 on
 * /community-alerts: a page route that throws for a signed-in user
 * is otherwise invisible to the authorization-focused tests.
 * Every non-parameterized GET route must render for the role that
 * owns it — and every public page must render for guests.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public static function publicPages(): array
    {
        return [
            'home' => ['/'],
            'news' => ['/news'],
            'wanted-list' => ['/wanted-list'],
            'experiences' => ['/experiences'],
            // Issue #343: /fraud-alerts removed — a "coming soon" Route::view
            // linked from nothing. Keeping it here would have pinned dead
            // weight as a feature.
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot-password' => ['/forgot-password'],
            'health' => ['/up'],
        ];
    }

    /**
     * @dataProvider publicPages
     */
    public function test_public_pages_render_for_guests(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    public static function userPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'alerts index' => ['/alerts'],
            'alerts create' => ['/alerts/create'],
            'alerts map' => ['/alerts/map'],
            'experiences create' => ['/experiences/create'],
            'my-history' => ['/my-history'],
            'notifications' => ['/notifications'],
            'notifications unread' => ['/notifications/unread'],
            'profile' => ['/profile'],
            'support index' => ['/support'],
            'support create' => ['/support/create'],
            // /verify-email 302s to /dashboard when the email is already
            // verified (Breeze behaviour); verified users are the factory default.
            'confirm-password' => ['/confirm-password'],
        ];
    }

    /**
     * @dataProvider userPages
     */
    public function test_authenticated_pages_render(string $uri): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($uri);

        // /notifications/unread is a JSON endpoint; the rest are views.
        $this->assertTrue(
            $response->status() === 200,
            "GET {$uri} returned {$response->status()}"
        );
    }

    public function test_verify_email_notice_status_depends_on_verification(): void
    {
        // Breeze redirects already-verified users away from the notice page.
        $verified = User::factory()->create();
        $this->actingAs($verified)->get('/verify-email')->assertRedirect('/dashboard');

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/verify-email')->assertOk();
    }

    public function test_admin_pages_render_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['/admin/alerts', '/admin/experiences', '/admin/support'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertOk();
        }
    }

    public function test_admin_pages_reject_regular_users(): void
    {
        $user = User::factory()->create();

        // Both admin mechanisms in use (middleware 'admin' and 'can:admin')
        // must deny the same callers.
        $this->actingAs($user)->get('/admin/alerts')->assertForbidden();
        $this->actingAs($user)->get('/admin/experiences')->assertForbidden();
        $this->actingAs($user)->get('/admin/support')->assertForbidden();
    }

    public function test_detail_pages_render_with_real_content(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id, 'name' => 'Na', 'title' => 'T', 'content' => 'C', 'status' => 'approved',
        ]);
        $alert = Alert::create([
            'user_id' => $user->id, 'title' => 'A', 'description' => 'D', 'status' => 'approved',
        ]);

        $this->get("/experiences/{$experience->id}")->assertOk();
        $this->actingAs($user)->get("/alerts/{$alert->id}")->assertOk();
    }
}
