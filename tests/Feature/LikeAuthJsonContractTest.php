<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #207: the three like fetch clients (public/js/alerts_show.js,
 * public/js/experiences_show.js, resources/views/comments/_item.blade.php)
 * all branch on `data.redirect` to bounce a stale-session click to login.
 * The contract was unreachable: /like and /like/unlike sit in the `auth`
 * middleware group, so Authenticate stopped guests BEFORE the controller —
 * LikeController::destroy's own redirect-401 branch was dead code, and
 * Laravel's default render of the middleware's AuthenticationException is
 * `{"message":"Unauthenticated."}` with no success/redirect keys, so every
 * handler fell through to the generic "API error" alert. The fix renders
 * JSON auth failures centrally (bootstrap/app.php) into the shape the
 * clients already consume, and gives store()'s unverified 403 a redirect
 * to the verification notice — these pins prove both, with the untouched
 * guest-HTML redirect as the control.
 */
class LikeAuthJsonContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_json_like_posts_answer_the_contract_the_clients_consume(): void
    {
        foreach (['/like', '/like/unlike'] as $url) {
            $this->postJson($url, ['type' => 'alert', 'id' => 1])
                ->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'redirect' => route('login'),
                ]);
        }
    }

    public function test_guest_html_like_post_keeps_the_redirect_to_login(): void
    {
        // Control: the renderer only claims JSON requests; the
        // Authenticate middleware's guest redirect is untouched.
        $this->post('/like', ['type' => 'alert', 'id' => 1])
            ->assertRedirect(route('login'));
    }

    public function test_unverified_like_403_redirects_to_the_verification_notice(): void
    {
        // Signed in but gated by #129: the useful destination is the email
        // verification page, not /login — otherwise the handler fell to the
        // generic "API error" alert with no way out.
        $unverified = User::factory()->unverified()->create();
        $alert = Alert::create([
            'user_id' => User::factory()->create()->id,
            'title' => 't',
            'description' => 'd',
            'status' => 'approved',
        ]);

        $this->actingAs($unverified)
            ->postJson('/like', ['type' => 'alert', 'id' => $alert->id])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Bạn cần xác thực email để thích bài viết.',
                'redirect' => route('verification.notice'),
            ]);

        $this->assertSame(0, Like::count());
    }

    public function test_verified_guest_json_on_other_auth_endpoints_shares_the_contract(): void
    {
        // The renderer is central, so the chatbot's guest path (pinned only
        // on its 401 status by ChatbotTest) now carries the same keys.
        $this->postJson('/chatbot/ask', ['question' => 'hi'])
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'redirect' => route('login')]);
    }
}
