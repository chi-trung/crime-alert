<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_verified_user_sees_own_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('myAlerts')
            ->assertViewHas('typePercents')
            ->assertViewHas('myExperience');

        // Admin-only aggregates must not leak into the regular-user payload.
        $this->actingAs($user)->get('/dashboard')->assertViewMissing('totalAlerts');
    }

    public function test_admin_sees_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertViewHas('totalAlerts')
            ->assertViewHas('pendingAlerts')
            ->assertViewHas('typePercentsAdmin')
            ->assertViewHas('createdData')
            ->assertViewHas('approvedData');
    }

    public function test_admin_dashboard_reflects_real_counts(): void
    {
        $admin = User::factory()->admin()->create();
        Alert::create(['user_id' => $admin->id, 'title' => 'a', 'description' => 'd', 'type' => 'Trộm cắp', 'status' => 'approved']);
        Alert::create(['user_id' => $admin->id, 'title' => 'b', 'description' => 'd', 'type' => 'Khong ro', 'status' => 'pending']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('totalAlerts', 2);
        $response->assertViewHas('pendingAlerts', 1);
        $response->assertViewHas('approvedAlerts', 1);
    }

    public function test_user_dashboard_counts_only_approved_this_month(): void
    {
        $user = User::factory()->create();
        Alert::create(['user_id' => $user->id, 'title' => 'ok', 'description' => 'd', 'type' => 'Lừa đảo', 'status' => 'approved']);
        Alert::create(['user_id' => $user->id, 'title' => 'pend', 'description' => 'd', 'status' => 'pending']);
        Experience::create(['user_id' => $user->id, 'name' => 'Na', 'title' => 'e', 'content' => 'c', 'status' => 'approved']);

        $response = $this->actingAs($user)->get('/dashboard');

        // myApproved: approved alerts this month = 1
        $response->assertViewHas('myApproved', 1);
        // totalPosts counts this month: 1 approved alert + 1 experience
        $response->assertViewHas('totalPosts', 2);
        // totalApprovedPosts: 1 alert + approved experiences = 2
        $response->assertViewHas('totalApprovedPosts', 2);
    }
}
