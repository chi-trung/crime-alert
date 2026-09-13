<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\Like;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #162: liking an approved experience whose user_id is NULL 500'd.
 * The self-notify guard `$model->user_id != $user->id` is a LOOSE compare,
 * so null != int evaluated true and the next line dereferenced the null
 * belongsTo: "Error: Call to a member function notify() on null". Because
 * the notify sits inside the #139 DB::transaction, the like insert rolled
 * back with the exception — the legacy post was permanently un-likable for
 * every user. NULL-owner rows are deliberate production data: experiences
 * .user_id is nullable (2025_06_25_132717) and the #47 FK migration's own
 * comment says the NULL rows are kept because they predate per-user
 * accounts, while experiences/show.blade.php still renders the like button
 * for them. Fix: require a truthy owner before entering the notify branch,
 * mirroring CommentController's #153 `$postOwnerId &&` gate.
 */
class NullOwnerLikeTest extends TestCase
{
    use RefreshDatabase;

    private function nullOwnerExperience(): Experience
    {
        return Experience::create([
            'user_id' => null,
            'name' => 'Legacy Author',
            'title' => 't',
            'content' => 'c',
            'status' => 'approved',
        ]);
    }

    public function test_like_on_null_owner_experience_records_and_does_not_500(): void
    {
        $liker = User::factory()->create();
        $experience = $this->nullOwnerExperience();

        // Pre-fix: 500 "Call to a member function notify() on null" and the
        // #139 transaction rolled the insert back (0 rows).
        $this->actingAs($liker)
            ->postJson('/like', ['type' => 'experience', 'id' => $experience->id])
            ->assertOk()
            ->assertJson(['success' => true, 'count' => 1]);

        $this->assertSame(1, Like::where('likeable_type', Experience::class)->where('likeable_id', $experience->id)->count());
        // Nobody is notified — there is no author to notify.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_liked_count_reflects_the_recorded_like(): void
    {
        // The permanent-un-likability was user-visible: every click 500'd and
        // the button never incremented. Post-fix the JSON count is real.
        $liker = User::factory()->create();
        $experience = $this->nullOwnerExperience();

        $this->actingAs($liker)->postJson('/like', ['type' => 'experience', 'id' => $experience->id])->assertOk();
        $this->actingAs($liker)->postJson('/like', ['type' => 'experience', 'id' => $experience->id])
            ->assertOk()->assertJson(['count' => 1]);

        $this->assertSame(1, $experience->likes()->count());
    }

    public function test_owned_experience_like_still_notifies_the_author(): void
    {
        // Control: the truthy-owner guard must not silence the normal path —
        // an owned post's author still gets exactly one bell from a stranger.
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id, 'name' => 'N', 'title' => 't', 'content' => 'c', 'status' => 'approved',
        ]);

        $this->actingAs($liker)->postJson('/like', ['type' => 'experience', 'id' => $experience->id])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, Like::count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $owner->id)->count());
    }
}
