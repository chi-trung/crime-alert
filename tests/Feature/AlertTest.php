<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    private function alertPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Ke gian tranh tien',
            'description' => 'Mo ta canh bao',
            'confirmCheckbox' => '1',
        ], $overrides);
    }

    public function test_guests_cannot_reach_alert_creation_or_admin(): void
    {
        $this->get('/alerts/create')->assertRedirect('/login');
        $this->post('/alerts', $this->alertPayload())->assertRedirect('/login');
        $this->get('/admin/alerts')->assertRedirect('/login');
    }

    public function test_verified_user_alert_lands_as_pending_and_notifies_admins(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload(['type' => 'Lừa đảo']))
            ->assertRedirect(route('alerts.create'));

        $alert = Alert::firstOrFail();
        $this->assertSame('pending', $alert->status);
        $this->assertSame($user->id, $alert->user_id);

        Notification::assertSentTo($admin, NewPostPendingApprovalNotification::class);
        Notification::assertNotSentTo($user, NewPostPendingApprovalNotification::class);
    }

    public function test_admin_alert_is_auto_approved_and_notifies_regular_users(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->post('/alerts', $this->alertPayload());

        $this->assertSame('approved', Alert::firstOrFail()->status);
        Notification::assertSentTo($user, NewPostNotification::class);
        Notification::assertNotSentTo($admin, NewPostNotification::class);
    }

    public function test_unverified_user_cannot_store_alerts(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/alerts', $this->alertPayload())
            ->assertSessionHas('error');

        $this->assertSame(0, Alert::count());
    }

    public function test_store_requires_confirmation_checkbox(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/alerts', ['title' => 'x', 'description' => 'y'])
            ->assertSessionHasErrors('confirmCheckbox');

        $this->assertSame(0, Alert::count());
    }

    public function test_index_only_shows_approved_alerts_and_filters_by_type(): void
    {
        $user = User::factory()->create();
        $approved = Alert::create(['user_id' => $user->id, 'title' => 'Cong khai', 'description' => 'd', 'status' => 'approved', 'type' => 'Trộm cắp']);
        $pending = Alert::create(['user_id' => $user->id, 'title' => 'Cho duyet', 'description' => 'd', 'status' => 'pending', 'type' => 'Trộm cắp']);
        $other = Alert::create(['user_id' => $user->id, 'title' => 'Loai khac', 'description' => 'd', 'status' => 'approved', 'type' => 'Bạo lực']);

        // /alerts sits inside the auth group.
        $this->actingAs($user)->get('/alerts')->assertOk()
            ->assertSee($approved->title)
            ->assertSee($other->title)
            ->assertDontSee($pending->title);

        // Type filter narrows to one approved alert of that type.
        $this->actingAs($user)->get('/alerts?type=Bạo lực')->assertOk()
            ->assertSee($other->title)
            ->assertDontSee($approved->title);
    }

    public function test_owner_and_admin_can_edit_but_strangers_cannot(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($owner)->get("/alerts/{$alert->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/alerts/{$alert->id}/edit")->assertOk();
        $this->actingAs($stranger)->get("/alerts/{$alert->id}/edit")->assertForbidden();
    }

    public function test_stranger_cannot_update_or_delete_and_payload_is_ignored(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Giữ nguyên', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($stranger)->put("/alerts/{$alert->id}", [
            'title' => 'Bị danh cap',
            'description' => 'x',
        ])->assertForbidden();

        $this->actingAs($stranger)->delete("/alerts/{$alert->id}")->assertForbidden();

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'title' => 'Giữ nguyên',
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_update_own_alert(): void
    {
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Cu', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi',
            'description' => 'd2',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('Moi', $alert->fresh()->title);
    }

    public function test_update_ignores_client_supplied_old_image(): void
    {
        // Issue #29: without an upload or a removal request, the image column
        // used to be written from the client-supplied `old_image` field. It
        // must keep the stored value no matter what is posted.
        $owner = User::factory()->create();
        $alert = Alert::create([
            'user_id' => $owner->id, 'title' => 'Cu', 'description' => 'd',
            'status' => 'pending', 'image' => 'alerts/real.png',
        ]);

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi',
            'description' => 'd2',
            'old_image' => 'https://evil.example/x.png',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('alerts/real.png', $alert->fresh()->image);

        // Same via the admin route, and with no image stored at all.
        $admin = User::factory()->admin()->create();
        $blank = Alert::create(['user_id' => $admin->id, 'title' => 'a', 'description' => 'd', 'status' => 'approved']);
        $this->actingAs($admin)->put("/admin/alerts/{$blank->id}", [
            'title' => 'b', 'description' => 'd', 'old_image' => '../../bootstrap/app.php',
        ]);
        $this->assertNull($blank->fresh()->image);
    }

    public function test_plain_edit_with_hidden_remove_image_zero_keeps_the_image(): void
    {
        // Issue #125: the edit form posts a hidden remove_image=0 on every
        // submission; the JS only flips it to 1 when the user clicks the
        // remove button. The old has('remove_image') check treated the
        // always-present '0' as an explicit removal, so every ordinary edit
        // deleted the stored file and nulled the column.
        Storage::fake('public');
        $owner = User::factory()->create();
        $path = 'alerts/keep-me.png';
        Storage::disk('public')->put($path, 'png-bytes');
        $alert = Alert::create([
            'user_id' => $owner->id, 'title' => 'Cu', 'description' => 'd',
            'status' => 'approved', 'image' => $path,
        ]);

        // The exact payload the browser sends for a text-only edit.
        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi',
            'description' => 'd2',
            'remove_image' => '0',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame($path, $alert->fresh()->image);
        Storage::disk('public')->assertExists($path);

        // An explicit removal (what the JS sets on click) must still delete.
        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi',
            'description' => 'd2',
            'remove_image' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertNull($alert->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_can_approve_and_reject(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = Alert::create(['user_id' => $admin->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);
        $pending2 = Alert::create(['user_id' => $admin->id, 'title' => 'b', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/alerts/{$pending->id}/approve")->assertRedirect();
        $this->assertSame('approved', $pending->fresh()->status);

        $this->actingAs($admin)->post("/admin/alerts/{$pending2->id}/reject")->assertRedirect();
        $this->assertSame('rejected', $pending2->fresh()->status);
    }

    public function test_regular_users_cannot_reach_admin_approval_routes(): void
    {
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => $user->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($user)->get('/admin/alerts')->assertForbidden();
        $this->actingAs($user)->post("/admin/alerts/{$alert->id}/approve")->assertForbidden();
        $this->assertSame('pending', $alert->fresh()->status);
    }

    public function test_non_admin_approve_reject_are_rejected_without_the_route_guard(): void
    {
        // Issue #117: approve()/reject() carried no in-method authorization,
        // so with the route middleware bypassed a non-admin's POST flipped the
        // status. The end-to-end route guard already 403s (test above), so
        // these pin the in-method check itself. Approving one's own pending
        // post grants no admin rights.
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $approveTarget = Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);
        $rejectTarget = Alert::create(['user_id' => $owner->id, 'title' => 'b', 'description' => 'd', 'status' => 'pending']);
        $this->withoutMiddleware(AdminMiddleware::class);

        $this->actingAs($stranger)->post("/admin/alerts/{$approveTarget->id}/approve")->assertForbidden();
        $this->assertSame('pending', $approveTarget->fresh()->status);

        $this->actingAs($owner)->post("/admin/alerts/{$approveTarget->id}/approve")->assertForbidden();
        $this->assertSame('pending', $approveTarget->fresh()->status);

        $this->actingAs($stranger)->post("/admin/alerts/{$rejectTarget->id}/reject")->assertForbidden();
        $this->assertSame('pending', $rejectTarget->fresh()->status);
    }

    public function test_admin_approve_reject_succeed_on_the_in_method_check_alone(): void
    {
        // Positive control for #117: with the route guard bypassed, a real
        // admin still passes the in-method check through both transitions.
        $admin = User::factory()->admin()->create();
        $approveTarget = Alert::create(['user_id' => $admin->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);
        $rejectTarget = Alert::create(['user_id' => $admin->id, 'title' => 'b', 'description' => 'd', 'status' => 'pending']);
        $this->withoutMiddleware(AdminMiddleware::class);

        $this->actingAs($admin)->post("/admin/alerts/{$approveTarget->id}/approve")->assertRedirect();
        $this->assertSame('approved', $approveTarget->fresh()->status);

        $this->actingAs($admin)->post("/admin/alerts/{$rejectTarget->id}/reject")->assertRedirect();
        $this->assertSame('rejected', $rejectTarget->fresh()->status);
    }

    public function test_owner_update_keeps_status_untouched_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $alert = Alert::create(['user_id' => $admin->id, 'title' => 'Cu', 'description' => 'd', 'status' => 'approved']);

        // Admin edit via the admin route: no moderation re-review required.
        $this->actingAs($admin)->put("/admin/alerts/{$alert->id}", [
            'title' => 'Moi',
            'description' => 'd2',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('approved', $alert->fresh()->status);
    }

    public function test_owner_edit_resets_approved_alert_to_pending(): void
    {
        // Issue #23: rewriting an approved alert must demote it to pending so
        // the new text passes moderation before it's public again.
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Da duyet', 'description' => 'd', 'status' => 'approved']);

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Noi dung moi',
            'description' => 'd2',
        ])->assertRedirect(route('dashboard'));

        $alert->refresh();
        $this->assertSame('pending', $alert->status);
        $this->assertSame('Noi dung moi', $alert->title);

        // Pending content is no longer listed publicly.
        $this->actingAs(User::factory()->create())->get('/alerts')->assertDontSee('Noi dung moi');
    }

    public function test_unapproved_alert_is_visible_only_to_owner_and_admin(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);

        // /alerts/{alert} sits in the auth group, so a stranger reaching an
        // unapproved post must get 403 (issue #20), not a public view.
        $this->actingAs($stranger)->get("/alerts/{$alert->id}")->assertForbidden();
        $this->actingAs($owner)->get("/alerts/{$alert->id}")->assertOk();
        $this->actingAs($admin)->get("/alerts/{$alert->id}")->assertOk();

        // Once approved it's readable by any signed-in user.
        $alert->update(['status' => 'approved']);
        $this->actingAs($stranger)->get("/alerts/{$alert->id}")->assertOk();
    }

    public function test_guest_is_redirected_from_alert_detail(): void
    {
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'approved']);

        $this->get("/alerts/{$alert->id}")->assertRedirect('/login');
    }

    public function test_store_rejects_junk_coordinates_and_oversized_type(): void
    {
        // Issue #31: type/latitude/longitude were persisted without any rule.
        $user = User::factory()->create();

        $this->actingAs($user)->post('/alerts', $this->alertPayload([
            'latitude' => 'not-a-number',
            'longitude' => '99999999',
        ]))->assertSessionHasErrors(['latitude', 'longitude']);

        $this->actingAs($user)->post('/alerts', $this->alertPayload([
            'type' => str_repeat('x', 400),
        ]))->assertSessionHasErrors('type');

        $this->assertSame(0, Alert::count());
    }

    public function test_update_rejects_junk_coordinates_and_keeps_clean_values(): void
    {
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Cu', 'description' => 'd', 'status' => 'pending']);

        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi', 'description' => 'd2', 'latitude' => '1000',
        ])->assertSessionHasErrors('latitude');
        $this->assertSame('Cu', $alert->fresh()->title);

        // A legitimate coordinate pair (the JS geocoder sends these) still works.
        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi', 'description' => 'd2',
            'latitude' => '10.7626220', 'longitude' => '106.6601720',
        ])->assertRedirect(route('dashboard'));
        $fresh = $alert->fresh();
        $this->assertEqualsWithDelta(10.762622, (float) $fresh->latitude, 0.0001);
        $this->assertEqualsWithDelta(106.660172, (float) $fresh->longitude, 0.0001);
    }

    public function test_description_is_length_bounded_on_store_and_update(): void
    {
        // Issue #37: a TEXT column with no validation bound — oversized
        // payloads either blow up on MySQL or bloat storage.
        $user = User::factory()->create();

        $this->actingAs($user)->post('/alerts', $this->alertPayload([
            'description' => str_repeat('x', 10001),
        ]))->assertSessionHasErrors('description');
        $this->assertSame(0, Alert::count());

        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'Cu', 'description' => 'd', 'status' => 'pending']);
        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi', 'description' => str_repeat('x', 10001),
        ])->assertSessionHasErrors('description');
        $this->assertSame('d', $alert->fresh()->description);

        // Exactly at the bound is still accepted.
        $this->actingAs($owner)->put("/alerts/{$alert->id}", [
            'title' => 'Moi', 'description' => str_repeat('x', 10000),
        ])->assertRedirect(route('dashboard'));
        $this->assertSame(10000, mb_strlen($alert->fresh()->description));
    }

    public function test_view_count_is_not_fillable(): void
    {
        // Issue #49: 'view_count' sat in $fillable but no migration ever
        // created the column. While listed, any mass-assignment path that
        // includes the key produces "Unknown column" SQL errors; removing
        // it makes fill()/save() silently drop the junk key instead.
        $owner = User::factory()->create();
        $alert = Alert::create(['user_id' => $owner->id, 'title' => 'a', 'description' => 'd', 'status' => 'pending']);

        $this->assertFalse($alert->isFillable('view_count'));

        // Before the fix this save() built an UPDATE ... SET view_count = 99
        // and died with a QueryException.
        $alert->fill(['view_count' => 99, 'title' => 'Sạch'])->save();
        $this->assertSame('Sạch', $alert->fresh()->title);
    }

    public function test_index_ignores_a_client_supplied_status_filter(): void
    {
        // Issue #41: the branch used to AND a second status clause onto the
        // constant 'approved', so any value — pending, rejected, garbage —
        // returned a blank list. The contract is approved-only, always.
        $user = User::factory()->create();
        $approved = Alert::create(['user_id' => $user->id, 'title' => 'Cong khai', 'description' => 'd', 'status' => 'approved']);
        $pending = Alert::create(['user_id' => $user->id, 'title' => 'Cho duyet', 'description' => 'd', 'status' => 'pending']);

        foreach (['pending', 'rejected', 'approved', 'anything'] as $status) {
            $this->actingAs($user)->get("/alerts?status={$status}")->assertOk()
                ->assertSee($approved->title)
                ->assertDontSee($pending->title);
        }
    }

    public function test_admin_queue_avatar_initial_is_a_full_vietnamese_letter(): void
    {
        // Issue #131: the avatar used byte substr($name, 0, 1). Names
        // starting with Đ (Đặng, Đào, Đình — very common) begin with the
        // 2-byte C4 90, so the cut left a lone 0xC4 lead byte; e() rejects
        // invalid UTF-8 and the avatar rendered empty. Lowercase names also
        // never uppercased. navigation.blade.php:161's profile avatar
        // already uses the mb idiom; the admin queue now matches it.
        $admin = User::factory()->admin()->create();
        foreach (['Đặng Văn Thành', 'trần minh', 'Nguyễn Thị Bình'] as $i => $name) {
            $author = User::factory()->create(['name' => $name]);
            Alert::create([
                'user_id' => $author->id,
                'title' => "Avatar Initial {$i}",
                'description' => 'd',
                'status' => 'pending',
            ]);
        }

        $html = $this->actingAs($admin)->get('/admin/alerts')->assertOk()->getContent();

        // The whole page must stay valid UTF-8 — the fix's whole point is
        // no lone lead bytes reach the browser.
        $this->assertTrue(mb_check_encoding($html, 'UTF-8'), 'admin queue rendered invalid UTF-8');

        $count = preg_match_all('/avatar-title[^>]*>([^<]*)</', $html, $matches);
        $this->assertSame(3, $count);
        $initials = array_map('trim', $matches[1]);
        sort($initials); // byte order: N, T, Đ — engine-independent
        $this->assertSame(['N', 'T', 'Đ'], $initials, 'avatar initials: expected Đ (2-byte letter), T (uppercased), N (control)');
    }
}
