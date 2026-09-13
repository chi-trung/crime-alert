<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #149: the live-chat script block sat in @push('scripts'), but
 * layouts/app.blade.php only has @yield('scripts') — no @stack('scripts')
 * exists anywhere, so the whole block (3s poll + AJAX submit handler) was
 * dropped from the rendered page. Now a proper @section('scripts') like
 * every other view; these tests assert the JavaScript actually ships.
 */
class SupportChatScriptsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_page_ships_the_live_chat_script(): void
    {
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'S']);

        $this->actingAs($owner)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('function fetchMessages()', false)
            ->assertSee('setInterval(fetchMessages, 3000)', false)
            ->assertDontSee("@push('scripts')", false);
    }

    public function test_admin_view_ships_the_same_script(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = SupportRequest::create(['user_id' => User::factory()->create()->id, 'subject' => 'S']);

        $this->actingAs($admin)
            ->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('function fetchMessages()', false);
    }
}
