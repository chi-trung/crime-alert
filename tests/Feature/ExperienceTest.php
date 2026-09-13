<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function experienceFor(User $user, string $status = 'pending'): Experience
    {
        return Experience::create([
            'user_id' => $user->id,
            'name' => 'Na',
            'title' => 'Bai chia se',
            'content' => 'Noi dung',
            'status' => $status,
        ]);
    }

    public function test_unapproved_experience_is_visible_only_to_owner_and_admin(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $experience = $this->experienceFor($owner, 'pending');

        $this->actingAs($owner)->get("/experiences/{$experience->id}")->assertOk();
        $this->actingAs($admin)->get("/experiences/{$experience->id}")->assertOk();
        $this->actingAs($stranger)->get("/experiences/{$experience->id}")->assertForbidden();

        // Approved posts are public.
        $experience->update(['status' => 'approved']);
        $this->get("/experiences/{$experience->id}")->assertOk();
    }

    public function test_stranger_cannot_edit_update_or_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $experience = $this->experienceFor($owner, 'approved');

        $this->actingAs($stranger)->get("/experiences/{$experience->id}/edit")->assertForbidden();
        $this->actingAs($stranger)->put("/experiences/{$experience->id}", [
            'title' => 'Doi tral', 'content' => 'x', 'name' => 'Y',
        ])->assertForbidden();
        $this->actingAs($stranger)->delete("/experiences/{$experience->id}")->assertForbidden();

        $this->assertDatabaseHas('experiences', ['id' => $experience->id, 'title' => 'Bai chia se']);
    }

    public function test_owner_and_admin_can_modify(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $experience = $this->experienceFor($owner);

        $this->actingAs($owner)->get("/experiences/{$experience->id}/edit")->assertOk();
        $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'Da sua', 'content' => 'x', 'name' => 'Y',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Da sua', $experience->fresh()->title);

        // Admin can delete someone else's post via the admin route.
        $this->actingAs($admin)->delete("/admin/experiences/{$experience->id}");
        $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
    }

    public function test_admin_approval_flow_for_experiences(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $experience = $this->experienceFor($user, 'pending');

        $this->actingAs($user)->post("/admin/experiences/{$experience->id}/approve")->assertForbidden();
        $this->assertSame('pending', $experience->fresh()->status);

        $this->actingAs($admin)->post("/admin/experiences/{$experience->id}/approve");
        $this->assertSame('approved', $experience->fresh()->status);

        // Issue #189: the old flow then rejected THIS row to prove reject
        // works — but that click is exactly the stale un-moderation #189
        // removed (an approved post can no longer be flipped to rejected by a
        // second click). Reject now needs its own pending row.
        $toReject = $this->experienceFor($user, 'pending');
        $this->actingAs($admin)->post("/admin/experiences/{$toReject->id}/reject");
        $this->assertSame('rejected', $toReject->fresh()->status);
    }

    public function test_non_admin_approve_reject_are_rejected_without_the_route_guard(): void
    {
        // Issue #117: approve()/reject() carried no in-method authorization,
        // so with the can:admin route middleware bypassed a non-admin's POST
        // flipped the status. The end-to-end route guard already 403s (test
        // above), so these pin the in-method check itself. Approving one's
        // own pending post grants no admin rights.
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $approveTarget = $this->experienceFor($owner, 'pending');
        $rejectTarget = $this->experienceFor($owner, 'pending');
        $this->withoutMiddleware(Authorize::class);

        $this->actingAs($stranger)->post("/admin/experiences/{$approveTarget->id}/approve")->assertForbidden();
        $this->assertSame('pending', $approveTarget->fresh()->status);

        $this->actingAs($owner)->post("/admin/experiences/{$approveTarget->id}/approve")->assertForbidden();
        $this->assertSame('pending', $approveTarget->fresh()->status);

        $this->actingAs($stranger)->post("/admin/experiences/{$rejectTarget->id}/reject")->assertForbidden();
        $this->assertSame('pending', $rejectTarget->fresh()->status);
    }

    public function test_admin_approve_reject_succeed_on_the_in_method_check_alone(): void
    {
        // Positive control for #117: with the route guard bypassed, a real
        // admin still passes the in-method check through both transitions.
        $admin = User::factory()->admin()->create();
        $approveTarget = $this->experienceFor($admin, 'pending');
        $rejectTarget = $this->experienceFor($admin, 'pending');
        $this->withoutMiddleware(Authorize::class);

        $this->actingAs($admin)->post("/admin/experiences/{$approveTarget->id}/approve");
        $this->assertSame('approved', $approveTarget->fresh()->status);

        $this->actingAs($admin)->post("/admin/experiences/{$rejectTarget->id}/reject");
        $this->assertSame('rejected', $rejectTarget->fresh()->status);
    }

    public function test_user_experience_lands_pending_and_admin_experience_auto_approves(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)->post('/experiences', [
            'title' => 'Cua user', 'content' => 'c', 'name' => 'U',
        ]);
        $this->assertSame('pending', Experience::where('title', 'Cua user')->value('status'));

        $this->actingAs($admin)->post('/experiences', [
            'title' => 'Cua admin', 'content' => 'c', 'name' => 'A',
        ]);
        $this->assertSame('approved', Experience::where('title', 'Cua admin')->value('status'));
    }

    public function test_admin_edit_keeps_status_and_owner_edit_demotes_to_pending(): void
    {
        // Issue #73: update() set status='pending' for every editor, so an
        // admin fixing a typo on an approved post threw it back into the
        // moderation queue — while the sibling AlertController deliberately
        // preserves admin-edit status. Both directions pinned here.
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $byAdmin = $this->experienceFor($owner, 'approved');
        $this->actingAs($admin)->put("/experiences/{$byAdmin->id}", [
            'title' => 'Admin sua loi chinh ta', 'content' => 'x', 'name' => 'Y',
        ])->assertSessionHasNoErrors();
        $this->assertSame('approved', $byAdmin->fresh()->status, 'admin edit must not re-open moderation');
        $this->assertSame('Admin sua loi chinh ta', $byAdmin->fresh()->title);

        $byOwner = $this->experienceFor($owner, 'approved');
        $this->actingAs($owner)->put("/experiences/{$byOwner->id}", [
            'title' => 'Owner sua noi dung', 'content' => 'x', 'name' => 'Y',
        ])->assertSessionHasNoErrors();
        $this->assertSame('pending', $byOwner->fresh()->status, 'owner edit of approved content must re-enter moderation');
    }

    public function test_content_is_length_bounded_on_store_and_update(): void
    {
        // Issue #37: `content` sits in a TEXT column; unbounded payloads used
        // to reach the database untouched.
        $owner = User::factory()->create();
        $huge = str_repeat('ạ', 10001);

        $this->actingAs($owner)->post('/experiences', [
            'title' => 'dai', 'content' => $huge, 'name' => 'U',
        ])->assertSessionHasErrors('content');
        $this->assertSame(0, Experience::count());

        $experience = $this->experienceFor($owner);
        $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'dai', 'content' => $huge, 'name' => 'U',
        ])->assertSessionHasErrors('content');
        $this->assertSame('Noi dung', $experience->fresh()->content);

        // The exact bound still saves (10000 chars of 3-byte text fits TEXT).
        $this->actingAs($owner)->put("/experiences/{$experience->id}", [
            'title' => 'vua du', 'content' => str_repeat('ạ', 10000), 'name' => 'U',
        ])->assertSessionHasNoErrors();
        $this->assertSame(10000, mb_strlen($experience->fresh()->content));
    }

    public function test_account_deletion_removes_the_owners_experiences(): void
    {
        // Issue #47: alerts/comments/likes/support all cascade on user
        // delete; experiences sat outside that contract and became ghosts.
        $user = User::factory()->create();
        $experience = $this->experienceFor($user, 'approved');

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('experiences', ['id' => $experience->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_experience_user_id_is_enforced_by_the_database(): void
    {
        // The cascade only works because the FK now exists; a ghost author
        // id must be rejected by the database itself, not just the model.
        $this->expectException(QueryException::class);

        Experience::create([
            'user_id' => 999_999,
            'name' => 'Ghost',
            'title' => 'Khong chu',
            'content' => 'c',
            'status' => 'approved',
        ]);
    }

    public function test_uploaded_picture_renders_on_the_detail_page(): void
    {
        // Issue #133: show.blade.php guarded $experience->image, but the
        // upload lands in the `avatar` column (store() line 64) — the hero
        // block was dead and the picture rendered nowhere.
        Storage::fake('public');
        $owner = User::factory()->create();
        $experience = $this->experienceFor($owner, 'approved');
        $experience->update(['avatar' => 'avatars/picture.png']);

        $this->get("/experiences/{$experience->id}")
            ->assertOk()
            ->assertSee('storage/avatars/picture.png', false);
    }

    public function test_pictureless_post_renders_no_hero_block(): void
    {
        // Guard control: the block is conditional, not unconditional markup.
        $owner = User::factory()->create();
        $experience = $this->experienceFor($owner, 'approved');

        $this->get("/experiences/{$experience->id}")
            ->assertOk()
            ->assertDontSee('alert-image-container', false);
    }

    public function test_index_still_lists_posts_with_an_avatar_author(): void
    {
        // Issue #133: the card img ternary read the nonexistent
        // users.avatar column; the fallback branch must still render every
        // listed post with the ui-avatars URL (no dead storage branch).
        $owner = User::factory()->create(['name' => 'Đặng Văn Test']);
        $experience = $this->experienceFor($owner, 'approved');

        $this->get('/experiences')
            ->assertOk()
            ->assertSee($experience->title)
            ->assertSee('ui-avatars.com/api/?name='.urlencode('Đặng Văn Test'), false)
            ->assertDontSee('storage/', false);
    }
}
