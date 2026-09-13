<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Models\WantedPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #145: the list filters feed $request input straight into string
 * concatenation — /wanted-list's #51 LIKE escape ($escaped.' %') and
 * /alerts' $likeWhere closure ('%'.$escaped.'%'), plus the type equality
 * binding. Request::filled() is true for non-empty arrays, so ?q[]=a
 * reached the concat and 500'd with 'Array to string conversion' — on
 * /wanted-list with no auth at all. Both index methods now validate the
 * free-form params as nullable strings: crafted arrays 302 back (or 422
 * to JSON callers) instead of crashing.
 */
class ArrayFilterParamTest extends TestCase
{
    use RefreshDatabase;

    public function test_wanted_list_plain_and_empty_search_still_work(): void
    {
        WantedPerson::create(['name' => 'Nguyen Van A', 'birth_year' => '1990']);

        $this->get('/wanted-list')->assertOk();
        $this->get('/wanted-list?q=Nguyen')->assertOk()->assertSee('Nguyen Van A');
        // The crafted case: pre-fix this 500s; now a redirect back with the error.
        $this->get('/wanted-list?q[]=a')->assertStatus(302)->assertSessionHasErrors('q');
    }

    public function test_alert_index_array_params_are_rejected_not_500(): void
    {
        $user = User::factory()->create();
        Alert::create(['user_id' => $user->id, 'title' => 'A', 'description' => 'd', 'status' => 'approved']);
        $this->actingAs($user);

        $this->get('/alerts')->assertOk();
        $this->get('/alerts?q[]=a')->assertStatus(302)->assertSessionHasErrors('q');
        $this->get('/alerts?location[]=a')->assertStatus(302)->assertSessionHasErrors('location');
        $this->get('/alerts?type[]=a')->assertStatus(302)->assertSessionHasErrors('type');
    }

    public function test_string_filters_keep_matching_after_the_type_guard(): void
    {
        // Control: the validation only rejects non-strings; the real filter
        // paths (scalar q with its #51 wildcard escaping, scalar type/location
        // equality) must still return the rows.
        $user = User::factory()->create();
        $alert = Alert::create(['user_id' => $user->id, 'title' => 'Cướp giật 50% đêm', 'description' => 'd', 'status' => 'approved', 'type' => 'Cướp giật', 'location' => 'Quận 1']);

        $this->actingAs($user)
            ->get('/alerts?q=Cướp')
            ->assertOk()
            ->assertSee($alert->title);
        $this->actingAs($user)
            ->get('/alerts?q=50%&type=Cướp giật&location=Quận')
            ->assertOk()
            ->assertSee($alert->title);
    }

    public function test_json_callers_get_422_instead_of_500(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/alerts?q[]=a')
            ->assertStatus(422)
            ->assertJsonValidationErrors('q');
    }
}
