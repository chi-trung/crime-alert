<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #256: close() was the last admin transition built on a snapshot
 * check-then-act — the #98 guard read `status` off the in-memory route
 * binding, then wrote blindly. A rival destroy() committed inside the
 * binding->UPDATE window touched 0 rows and still flashed "Đã đóng yêu
 * cầu!" for a thread that no longer exists, and a second close racing the
 * first flashed success to the loser as well. close() is now the #189
 * conditional UPDATE keyed on status='open' that approve()/reject() use,
 * with the two zero-match reasons told apart by an in-transaction re-read:
 * a vanished thread 404s (the #247 answer), an alive-closed one keeps the
 * #98 info flash. Race idiom per #163 (SupportSendMessageRaceTest).
 */
class SupportCloseRaceTest extends TestCase
{
    use RefreshDatabase;

    private function openThread(): SupportRequest
    {
        $owner = User::factory()->create();

        return SupportRequest::create(['user_id' => $owner->id, 'subject' => 's']);
    }

    public function test_destroy_committed_mid_close_404s_instead_of_a_false_success(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = $this->openThread();

        // Deliberately raw (the #163 note): an Eloquent delete would fire the
        // model events and the #102 notification sweep from inside our own
        // transaction's hydration; the rival here is a separate committed
        // request, and a raw delete models exactly its durable end-state.
        // where('id', ...), not whereKey — the builder would read a missing
        // whereKey as a dynamic filter on a "key" column and no-op the race.
        $armed = true;
        SupportRequest::retrieved(function (SupportRequest $model) use (&$armed, $thread): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            DB::table('support_requests')->where('id', $thread->id)->delete();
        });

        $this->actingAs($admin)
            ->post(route('admin.support.close', $thread))
            ->assertNotFound();

        $this->assertSame(0, SupportRequest::count());
    }

    public function test_second_close_winning_race_gets_the_info_flash_not_a_second_success(): void
    {
        // Both requests hydrate while the thread is open; the first commit
        // closes it, the second must hear "already closed" — the same answer
        // the serial double click already gives (#98), now race-proof.
        $admin = User::factory()->admin()->create();
        $thread = $this->openThread();

        $armed = true;
        SupportRequest::retrieved(function (SupportRequest $model) use (&$armed, $thread): void {
            if (! $armed || ! $model->exists) {
                return;
            }
            $armed = false;
            DB::table('support_requests')->where('id', $thread->id)->update(['status' => 'closed']);
        });

        $this->actingAs($admin)
            ->from(route('admin.support.index'))
            ->post(route('admin.support.close', $thread))
            ->assertRedirect(route('admin.support.index'))
            ->assertSessionHas('info', 'Yêu cầu này đã được đóng trước đó.')
            ->assertSessionHasNoErrors();

        // The rival's close stands; the loser wrote nothing.
        $this->assertDatabaseHas('support_requests', ['id' => $thread->id, 'status' => 'closed']);
    }

    public function test_first_honest_close_still_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = $this->openThread();

        $this->actingAs($admin)
            ->from(route('admin.support.index'))
            ->post(route('admin.support.close', $thread))
            ->assertRedirect(route('admin.support.index'))
            ->assertSessionHas('success', 'Đã đóng yêu cầu!');

        $this->assertDatabaseHas('support_requests', ['id' => $thread->id, 'status' => 'closed']);
    }
}
