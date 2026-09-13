<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #93: readAll() used to fetch the whole unread set through the
 * magic attribute and markAsRead() the collection, which proxies save()
 * to every model — one SELECT + one UPDATE per notification. The bulk
 * relation update must issue exactly one UPDATE over notifications and
 * still clear the backlog, including another user's rows' safety: the
 * WHERE clause is scoped to the acting user by the relation.
 */
class ReadAllBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_all_marks_every_unread_of_the_actor_in_one_update(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $alert = Alert::forceCreate([
            'user_id' => $other->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved',
        ]);
        foreach (range(1, 30) as $i) {
            $user->notify(new NewPostNotification($alert, $other, 'alert'));
        }
        // A same-shaped notification for someone else must stay unread.
        $other->notify(new NewPostNotification($alert, $other, 'alert'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $updates = array_filter($log, function (array $q) {
            return preg_match('/^update\s+["`\']?notifications\b/i', $q['query']);
        });
        // The old code produced one UPDATE per row (30 here); the relation
        // query builder must collapse the whole operation to a single one.
        $this->assertCount(1, $updates, 'readAll issued '.count($updates).' updates over notifications');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }
}
