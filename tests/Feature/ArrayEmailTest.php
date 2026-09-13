<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #160: the email rule lists on POST /register (RegisteredUserController)
 * and PATCH /profile (ProfileUpdateRequest) carried 'string' before
 * 'lowercase', but a failing 'string' rule does NOT stop the chain — only
 * implicit-rule failures or 'bail' halt it (Validator::shouldStopValidating).
 * So email[]=a@b.com still reached 'lowercase' -> Str::lower ->
 * mb_strtolower(array) -> TypeError 500. 'bail' now short-circuits the
 * attribute on the type failure. Control: forgot-password takes the same
 * array without crashing because its rules omit 'lowercase' — the contrast
 * the issue cites for localizing the trigger to that rule.
 */
class ArrayEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_array_email_on_register_is_rejected_not_500(): void
    {
        // Pre-fix: 500 'mb_strtolower(): Argument #1 ($string) must be of
        // type string, array given'.
        $this->post('/register', [
            'name' => 'Test',
            'email' => ['a@b.com'],
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertStatus(302)->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_array_email_json_on_register_gets_422(): void
    {
        $this->postJson('/register', [
            'name' => 'Test',
            'email' => ['a@b.com'],
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_array_email_on_profile_update_is_rejected_not_500(): void
    {
        $user = User::factory()->create();

        // Pre-fix: the identical mb_strtolower TypeError on PATCH /profile.
        // back() inside ProfileController::update needs an origin.
        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'New Name',
                'email' => ['x@y.com'],
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
    }

    public function test_forgot_password_array_email_still_422s(): void
    {
        // Control pinning the issue's contrast claim: this route never had
        // the bug because its rules have no 'lowercase'.
        $this->postJson('/forgot-password', ['email' => ['a@b.com']])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_valid_string_registration_still_works(): void
    {
        // Control: 'bail' must not break the happy path.
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'fresh@user.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'fresh@user.com')->firstOrFail();
        $this->assertTrue(Hash::check('Password!123', $user->password));
    }
}
