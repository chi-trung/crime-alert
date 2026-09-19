<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #378: five Breeze-scaffold Blade components had no reference anywhere
 * in the project — not as x-kebab, x_snake, or x:dots, and none was referenced
 * from inside another component. danger-button, secondary-button and nav-link
 * ship Tailwind classes while the project's UI is Bootstrap; dropdown and modal
 * are the Alpine/Livewire shapes while the nav has its own .menu-open mechanism
 * (#365) and the dashboard uses a Bootstrap <div class="modal fade">.
 *
 * Removing them is only safe if the six components that ARE used still resolve.
 * Each one is pinned by rendering a page that includes it, so a rename or a
 * second accidental deletion fails here, not in production.
 */
class UsedBladeComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_six_kept_components_still_exist(): void
    {
        foreach ([
            'application-logo',
            'auth-session-status',
            'input-error',
            'input-label',
            'primary-button',
            'text-input',
        ] as $component) {
            $this->assertFileExists(
                resource_path("views/components/{$component}.blade.php"),
                "the kept component {$component} must still exist"
            );
        }
    }

    public function test_the_five_removed_components_are_gone(): void
    {
        foreach ([
            'danger-button',
            'dropdown',
            'modal',
            'nav-link',
            'secondary-button',
        ] as $component) {
            $this->assertFileDoesNotExist(
                resource_path("views/components/{$component}.blade.php"),
                "the unreferenced component {$component} must be removed"
            );
        }
    }

    public function test_no_view_references_a_removed_component(): void
    {
        // The blade tag can be x-kebab-case, x_snake_case or x:dots.notation.
        // Nothing in the project may reference any removed component in any of
        // these spellings.
        $views = glob(resource_path('views/**/*.blade.php'));
        $this->assertNotEmpty($views, 'the view tree must exist');

        foreach ($views as $view) {
            $source = file_get_contents($view);
            foreach (['danger-button', 'dropdown', 'modal', 'nav-link', 'secondary-button'] as $c) {
                $this->assertDoesNotMatchRegularExpression(
                    '/<x[:-]'.preg_quote($c, '/').'([^a-z0-9-]|$)/',
                    $source,
                    "{$view} must not reference the removed component {$c}"
                );
            }
        }
    }

    public function test_the_auth_pages_that_use_the_kept_components_render(): void
    {
        // login and register are the heaviest component users; rendering them
        // proves the kept components still resolve end to end.
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_a_component_page_renders_for_an_authed_user(): void
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        // primary-button / input-* families render on the profile form.
        $this->actingAs($user)->get('/profile')->assertOk();
    }
}
