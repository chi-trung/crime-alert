<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Issue #63: this test used to exercise stock Breeze's `PUT /password` — a
 * second password-change door that skipped #27's rotation hygiene. That
 * route is gone; the suite now pins the surviving endpoint,
 * `POST /profile/change-password`, including the two effects that made the
 * stock door unsafe.
 */
class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function attackerArtifacts(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'attacker-session',
            'user_id' => $user->id,
            'ip_address' => '1.2.3.4',
            'user_agent' => 'curl/8',
            'payload' => 'stolen',
            'last_activity' => time(),
        ]);
        $user->forceFill(['remember_token' => 'ATTACKER_REMEMBER_COOKIE'])->save();
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/change-password', [
                'current_password' => 'password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->post('/profile/change-password', [
                'current_password' => 'wrong-password',
                'new_password' => 'new-password',
                'new_password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_rotation_evicts_other_sessions_and_kills_remember_tokens(): void
    {
        $user = User::factory()->create();
        $this->attackerArtifacts($user);

        $this->actingAs($user)->post('/profile/change-password', [
            'current_password' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'attacker-session']);
        $this->assertNull($user->refresh()->remember_token);
    }

    public function test_the_removed_stock_route_is_gone(): void
    {
        $user = User::factory()->create();
        $this->attackerArtifacts($user);

        // Issue #63's second door: the URI no longer exists at all (no
        // method matched), so the router answers 404.
        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(404);

        // The attack this issue was about: nothing rotated, attacker in place.
        $this->assertDatabaseHas('sessions', ['id' => 'attacker-session']);
        $this->assertSame('ATTACKER_REMEMBER_COOKIE', $user->refresh()->remember_token);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
