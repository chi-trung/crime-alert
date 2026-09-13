<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * Issue #205: the unique rule is a SELECT and the create below was
     * unguarded, so two concurrent same-email POSTs both validated and the
     * loser died on the users.email UNIQUE index as an uncaught
     * UniqueConstraintViolationException (HTTP 500). The repo's race idiom
     * (GhostLikeRaceTest #139): a creating hook that lands the rival row
     * inside this request's own validation-to-write window.
     */
    public function test_raced_duplicate_email_registration_returns_the_validation_error_not_a_500(): void
    {
        User::creating(function (User $user) {
            if ($user->email === 'twin@example.com') {
                DB::table('users')->insert([
                    'name' => 'Rival',
                    'email' => 'twin@example.com',
                    'password' => bcrypt('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'twin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Byte-for-byte the serial duplicate's outcome: 302 back carrying
        // the validation error, never the 500 error page.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::where('email', 'twin@example.com')->count());
    }

    public function test_serial_duplicate_email_registration_shows_the_same_validation_error(): void
    {
        // Baseline pinning the contract the race branch must mirror, and
        // the shape of the flashed message.
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertSame(
            'Trường email đã có trong cơ sở dữ liệu.',
            session('errors')->get('email')[0]
        );
        $this->assertGuest();
    }
}
