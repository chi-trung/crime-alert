<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($admin)->post("/admin/experiences/{$experience->id}/reject");
        $this->assertSame('rejected', $experience->fresh()->status);
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
}
