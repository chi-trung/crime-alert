<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Issue #159: NewPasswordController::store validated token as bare
 * 'required', so token[]=x reached Password::reset -> validateReset ->
 * DatabaseTokenRepository::exists() -> Hash::check -> password_verify
 * (array) -> TypeError 500 on the guest-reachable reset route. Same
 * array-input class as #154/#155 (password) and #145 (GET filters); the
 * reset token attribute was the remaining instance. store() now validates
 * ['required','string'] — and unlike #154, no 'bail' is needed because
 * 'string' is the last rule on the attribute, so the failing type rule is
 * what answers the request before the hasher ever sees the array.
 */
class ResetPasswordTokenStringTest extends TestCase
{
    use RefreshDatabase;

    /** Mint a live reset-token row the way a real visitor does. */
    private function resetTokenFor(User $user): string
    {
        Notification::fake();
        $this->post('/forgot-password', ['email' => $user->email]);
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    public function test_array_token_is_rejected_not_500(): void
    {
        // Pre-fix this returned 500 with the password_verify TypeError
        // (the row exists, so exists() reaches the hasher).
        $user = User::factory()->create();
        $this->resetTokenFor($user);

        $this->post('/reset-password', [
            'token' => ['not-a-string'],
            'email' => $user->email,
            'password' => 'rotated-secret-password',
            'password_confirmation' => 'rotated-secret-password',
        ])->assertStatus(302)->assertSessionHasErrors('token');
    }

    public function test_array_token_json_gets_422(): void
    {
        $user = User::factory()->create();
        $this->resetTokenFor($user);

        $this->postJson('/reset-password', [
            'token' => ['not-a-string'],
            'email' => $user->email,
            'password' => 'rotated-secret-password',
            'password_confirmation' => 'rotated-secret-password',
        ])->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_string_token_still_resets_normally(): void
    {
        // Control: the type guard must not break the happy path.
        $user = User::factory()->create();
        $token = $this->resetTokenFor($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'rotated-secret-password',
            'password_confirmation' => 'rotated-secret-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }
}
