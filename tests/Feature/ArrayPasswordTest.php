<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #154: the same array-input class #145 killed for GET filter params,
 * now on the two 'current_password' validation sites in ProfileController.
 * Request::filled() is true for non-empty arrays, so password[]=a /
 * current_password[]=a passed 'required' and the current_password rule ended
 * at password_verify(array) -> TypeError 'password_verify(): Argument #1
 * ($password) must be of type string, array given' -> 500. With 'string'
 * added to both rules — plus 'bail', because the Validator keeps running
 * later rules on an attribute even after one failed — crafted arrays 302
 * back with a validation error (422 to JSON callers) instead of crashing. The type guard fires before
 * the rule's Auth::logout()/account delete, so a rejected array can never
 * destroy the account either.
 * The orphaned Breeze ConfirmablePasswordController shares the shape but is
 * handled separately (it has no UI); it is deliberately not covered here.
 */
class ArrayPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_array_password_on_account_delete_is_rejected_not_500(): void
    {
        $user = User::factory()->create();

        // Pre-fix this 500s (the TypeError above); now a 302 back to the
        // profile page with the error in the 'userDeletion' bag — the same
        // idiom ProfileTest uses for a wrong password.
        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile', ['password' => ['a']])
            ->assertStatus(302)
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticatedAs($user);
    }

    public function test_array_password_on_account_delete_json_gets_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/profile', ['password' => ['a']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNotNull($user->fresh());
    }

    public function test_array_current_password_on_change_password_is_rejected_not_500(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/change-password', [
                'current_password' => ['a'],
                'new_password' => 'new-secret-password',
                'new_password_confirmation' => 'new-secret-password',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('current_password');

        // Rejected before the rule ran: the password must be untouched.
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_array_current_password_on_change_password_json_gets_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/profile/change-password', [
                'current_password' => ['a'],
                'new_password' => 'new-secret-password',
                'new_password_confirmation' => 'new-secret-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_string_passwords_still_work_after_the_type_guard(): void
    {
        // Control: 'string' must only reject non-strings — the happy paths
        // (correct current_password changes it; correct password deletes
        // the account) must not regress.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->post('/profile/change-password', [
                'current_password' => 'password',
                'new_password' => 'new-secret-password',
                'new_password_confirmation' => 'new-secret-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-secret-password', $user->fresh()->password));

        $user2 = User::factory()->create();

        $this->actingAs($user2)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user2->fresh());
    }
}
