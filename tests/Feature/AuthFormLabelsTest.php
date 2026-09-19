<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Issue #396: login and register used `placeholder` as the only label for six
 * inputs. A placeholder vanishes on the first keystroke and is not announced
 * as the field's name. Every other form in the app was closed by #384; the
 * three sibling Breeze pages (confirm-password, forgot-password,
 * reset-password) already shipped <x-input-label for=, so these two were the
 * inconsistent ones.
 *
 * Each test asserts the label exists, is bound by for/id to the right control,
 * and renders real text — not the empty <label></label> that a half-applied
 * fix leaves. The placeholders are kept as hints, so they are pinned too: the
 * fix adds labels, it does not trade them for the placeholder.
 */
class AuthFormLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_form_labels_its_two_inputs(): void
    {
        $html = $this->get(route('login'))
            ->assertOk()
            ->getContent();

        foreach (['email', 'password'] as $field) {
            $this->assertStringContainsString(
                'id="'.$field.'"',
                $html,
                "the login $field input must keep its id"
            );
            $this->assertMatchesRegularExpression(
                '/<label[^>]*for="'.$field.'"[^>]*>[^<]+<\/label>/',
                $html,
                "the login $field input must have a bound label with text"
            );
        }

        // The placeholder is still a useful hint — the fix adds a label, it
        // does not trade one for the other.
        $this->assertStringContainsString('placeholder="Mật khẩu"', $html);
    }

    public function test_the_register_form_labels_its_four_inputs(): void
    {
        $html = $this->get(route('register'))
            ->assertOk()
            ->getContent();

        foreach (['name', 'email', 'password', 'password_confirmation'] as $field) {
            $this->assertStringContainsString(
                'id="'.$field.'"',
                $html,
                "the register $field input must keep its id"
            );
            $this->assertMatchesRegularExpression(
                '/<label[^>]*for="'.$field.'"[^>]*>[^<]+<\/label>/',
                $html,
                "the register $field input must have a bound label with text"
            );
        }
    }

    public function test_the_register_name_label_uses_the_new_message_key(): void
    {
        $html = $this->get(route('register'))
            ->assertOk()
            ->getContent();

        // The label has to resolve through messages.full_name — a missing key
        // would leak the literal "messages.full_name" string into the page.
        $this->assertStringContainsString('Họ và tên', $html);
        $this->assertStringNotContainsString('messages.full_name', $html);
    }

    public function test_the_breeze_siblings_still_label_their_inputs(): void
    {
        // password.confirm is behind auth; password.request and password.reset
        // are behind guest, so they have to be fetched unsigned. Pinning them
        // guards the pattern this round made the whole auth/ folder
        // consistent with.
        $confirm = $this->actingAs(User::factory()->create())
            ->get(route('password.confirm'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<label[^>]*for="password"[^>]*>[^<]+<\/label>/',
            $confirm,
            'the Breeze confirm-password page still labels its input'
        );

        Auth::logout();

        foreach (['password.request', 'password.reset'] as $route) {
            $url = $route === 'password.reset'
                ? route($route, ['token' => 'any-token'])
                : route($route);

            $html = $this->get($url)
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/<label[^>]*for="email"[^>]*>[^<]+<\/label>/',
                $html,
                "the Breeze $route page still labels its input"
            );
        }
    }
}
