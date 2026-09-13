<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Models\WantedPerson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #51: user-typed search strings went into LIKE patterns without
 * escaping, so '%' and '_' acted as wildcards instead of literals. Values
 * were always bound (no injection), but the filtering itself was wrong:
 * q=50% matched any title with "50" anywhere, q=_ matched nearly anything,
 * q=% dumped the entire list.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function alert(User $user, string $title): Alert
    {
        return Alert::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => 'd',
            'status' => 'approved',
        ]);
    }

    public function test_alert_title_search_treats_percent_as_literal(): void
    {
        $user = User::factory()->create();
        $hit = $this->alert($user, 'Cat 50% lot');
        // 'Cat 500 lot' has no literal "50%" — the old unescaped pattern
        // '%50%%' matched it anyway ('%' swallowed "0 lot").
        $miss = $this->alert($user, 'Cat 500 lot');

        $this->actingAs($user)->get('/alerts?q=50%25')->assertOk()
            ->assertSee($hit->title)
            ->assertDontSee($miss->title);
    }

    public function test_bare_percent_search_matches_nothing_and_not_everything(): void
    {
        $user = User::factory()->create();
        $this->alert($user, 'Bat ky ten gi');

        // Before the fix, q=% built '%%%' — the whole table was returned.
        $this->actingAs($user)->get('/alerts?q=%25')->assertOk()
            ->assertDontSee('Bat ky ten gi');
    }

    public function test_alert_title_search_treats_underscore_as_literal(): void
    {
        $user = User::factory()->create();
        $hit = $this->alert($user, 'Nu_1');
        // 'Nua1' satisfies the wildcard pattern 'Nu_1' but not the literal.
        $miss = $this->alert($user, 'Nua1');

        $this->actingAs($user)->get('/alerts?q=Nu_1')->assertOk()
            ->assertSee($hit->title)
            ->assertDontSee($miss->title);
    }

    public function test_normal_substring_search_still_works(): void
    {
        $user = User::factory()->create();
        $hit = $this->alert($user, 'Ke gian tranh tien');
        $miss = $this->alert($user, 'Tin an toan');

        $this->actingAs($user)->get('/alerts?q=tranh%20tien')->assertOk()
            ->assertSee($hit->title)
            ->assertDontSee($miss->title);
    }

    public function test_wanted_list_search_treats_wildcards_as_literal(): void
    {
        $hit = WantedPerson::create(['name' => 'Nguyen 50% Van']);
        $miss = WantedPerson::create(['name' => 'Tran Van Binh']);

        $this->get('/wanted-list?q=50%25')->assertOk()
            ->assertSee($hit->name)
            ->assertDontSee($miss->name);

        // A bare wildcard must no longer return the full list.
        $this->get('/wanted-list?q=%25')->assertOk()
            ->assertDontSee($hit->name)
            ->assertDontSee($miss->name);
    }

    public function test_wanted_list_whole_word_matching_is_preserved(): void
    {
        $prefix = WantedPerson::create(['name' => 'Nguyen Van A']);
        $inside = WantedPerson::create(['name' => 'Co Nguyen Van A Day']);
        $suffix = WantedPerson::create(['name' => 'Hey Nguyen Van A']);
        $notAWord = WantedPerson::create(['name' => 'NguyenVanA']);

        $response = $this->get('/wanted-list?q=Nguyen%20Van%20A')->assertOk()
            ->assertSee($prefix->name)
            ->assertSee($inside->name)
            ->assertSee($suffix->name);

        // Not whitespace-delimited -> still excluded, as the old four
        // (five, one duplicated) patterns intended.
        $this->assertStringNotContainsString($notAWord->name, $response->getContent());
    }
}
