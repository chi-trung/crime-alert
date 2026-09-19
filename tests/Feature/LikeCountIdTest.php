<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #386: the like block shipped the same id twice per page — once in the
 *
 * @auth button, once in the guest <a>. The branches are mutually exclusive, so
 * only one renders and no live page had a duplicate; the defect was in the
 * template, which is exactly what makes it a trap: a refactor touching the
 * guest branch would have put two nodes side by side, and
 * alerts_show.js:60 / experiences_show.js:36 select that node by bare
 * getElementById. comments/_item.blade.php already used .like-count classes.
 */
class LikeCountIdTest extends TestCase
{
    use RefreshDatabase;

    private function approvedAlert(): Alert
    {
        return Alert::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Cảnh báo thử nghiệm',
            'description' => 'Mô tả.',
            'type' => 'theft',
            'status' => 'approved',
            'latitude' => 10.7626,
            'longitude' => 106.6602,
        ]);
    }

    public function test_the_alert_like_block_ships_no_duplicate_id_for_users(): void
    {
        $alert = $this->approvedAlert();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('alerts.show', $alert))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'id="like-count-alert"'),
            'like-count-alert must appear exactly once in the authed branch'
        );
    }

    public function test_the_experience_guest_like_link_ships_no_duplicate_id(): void
    {
        // experiences.show is the only one of the pair a guest can reach —
        // alerts.show sits inside the auth group, so its @else branch never
        // renders. Both templates still need the fix: a route change would
        // surface the alerts duplicate id with no other warning.
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'title' => 'Một tiêu đề',
            'content' => 'Nội dung.',
            'status' => 'approved',
        ]);

        $html = $this->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            0,
            substr_count($html, 'id="like-count-exp"'),
            'the guest branch must not ship the JS id at all — it has no JS'
        );
        $this->assertStringContainsString('class="like-count"', $html);
    }

    public function test_the_experience_authed_like_block_keeps_its_id(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'title' => 'Một tiêu đề',
            'content' => 'Nội dung.',
            'status' => 'approved',
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        // The authed branch is the one experiences_show.js:36 selects, so the
        // id has to survive — this pins that the fix did not delete it.
        $this->assertSame(1, substr_count($html, 'id="like-count-exp"'));
    }

    public function test_the_guest_like_link_is_accessible_without_hover(): void
    {
        $user = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'title' => 'Một tiêu đề',
            'content' => 'Nội dung.',
            'status' => 'approved',
        ]);

        // title= only shows on hover, so it is not an accessible name.
        $html = $this->get(route('experiences.show', $experience))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a[^>]*aria-label="[^"]*"/i',
            $html,
            'the guest like link must carry an aria-label, not only a hover title'
        );
    }
}
