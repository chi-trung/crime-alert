<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_cannot_be_set_through_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'secret123',
            'isAdmin' => true,
        ]);

        $this->assertFalse($user->fresh()->isAdmin);
    }

    public function test_is_admin_still_settable_explicitly(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['isAdmin' => true])->save();

        $this->assertTrue($user->fresh()->isAdmin);
    }

    public function test_registration_cannot_grant_admin_rights(): void
    {
        $this->post('/register', [
            'name' => 'PrivEsc',
            'email' => 'plies@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'isAdmin' => true,
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'plies@example.com')->firstOrFail();
        $this->assertFalse($user->isAdmin);
    }

    public function test_storage_urls_use_asset_helper_in_views(): void
    {
        // Regression: views used to hardcode "/storage/app/public/..." which
        // double-prefixes the disk root and 404s on every upload.
        $views = [
            resource_path('views/alerts/index.blade.php'),
            resource_path('views/alerts/show.blade.php'),
            resource_path('views/dashboard.blade.php'),
        ];

        foreach ($views as $view) {
            $this->assertStringNotContainsString(
                '/storage/app/public/',
                file_get_contents($view),
                "View {$view} still hardcodes the double-prefixed storage path."
            );
        }
    }
}
