<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #61: `notifications` is a morph relation, so unlike the likes of
 * issue #57 it was swept by nobody at all — account deletion left every
 * database notification the user had received keyed to a missing id.
 */
class NotificationOrphanTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $recipient, User $creator): void
    {
        $alert = Alert::create([
            'user_id' => $creator->id, 'title' => 'T', 'description' => 'd', 'status' => 'approved',
        ]);
        \Notification::send($recipient, new NewPostNotification($alert, $creator, 'alert'));
    }

    private function rowsFor(int $userId): int
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->count();
    }

    public function test_account_deletion_removes_the_departing_users_notifications(): void
    {
        $victim = User::factory()->create();
        $creator = User::factory()->create();
        $this->seedNotification($victim, $creator);
        $this->seedNotification($victim, $creator);

        $this->assertSame(2, $this->rowsFor($victim->id));

        $this->actingAs($victim)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertSame(0, $this->rowsFor($victim->id));
    }

    public function test_other_users_keep_their_notifications(): void
    {
        $victim = User::factory()->create();
        $survivor = User::factory()->create();
        $creator = User::factory()->create();
        $this->seedNotification($victim, $creator);
        $this->seedNotification($survivor, $creator);

        $this->actingAs($victim)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertSame(0, $this->rowsFor($victim->id));
        $this->assertSame(1, $this->rowsFor($survivor->id));
    }

    public function test_the_sweep_runs_outside_the_profile_route_too(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $this->seedNotification($user, $creator);

        // Plain model deletion (an admin console, a future importer) must get
        // the same cleanup as the HTTP route.
        $user->delete();

        $this->assertSame(0, $this->rowsFor($user->id));
    }
}
