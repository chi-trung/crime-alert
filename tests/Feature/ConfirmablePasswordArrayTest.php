<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #155: ConfirmablePasswordController::store passed $request->password
 * straight into the guard's validate(); password[]=a reached
 * password_verify(array) -> TypeError -> 500 for any authenticated session.
 * Same class as #154 (ProfileController) and #145 (GET filters); the route
 * is orphaned in UX terms (nothing links to the view, no middleware uses
 * 'confirmed') but registered live at /confirm-password. store() now rejects
 * non-string passwords before the guard call.
 */
class ConfirmablePasswordArrayTest extends TestCase
{
    use RefreshDatabase;

    public function test_array_password_is_rejected_not_500(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/confirm-password', ['password' => ['a']])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');
    }

    public function test_array_password_json_gets_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/confirm-password', ['password' => ['a']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_correct_string_password_still_confirms(): void
    {
        // Control: the type guard must not break the happy path — a correct
        // password stores auth.password_confirmed_at and redirects onward.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }
}
