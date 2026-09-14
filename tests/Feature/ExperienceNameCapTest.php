<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #224: store()/update() capped 'name' at max:100 while the forms
 * supply that value themselves (create posts a hidden Auth::user()->name,
 * edit re-posts the stored $experience->name) — and registration plus PATCH
 * /profile accept names up to max:255, the varchar(255) column's own width.
 * A user with a legal 101-255-char name therefore had their own identity
 * rejected by their own form, and invisibly: the only @error('name') block
 * lived in the guest @else branch of create.blade.php (dead markup — both
 * routes are auth-only) and edit had none, so the page just re-rendered
 * unchanged forever. The fix aligns the cap to 255 and surfaces
 *
 * @error('name') in both authed branches.
 */
class ExperienceNameCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_with_a_150_char_name_can_publish(): void
    {
        $longName = str_repeat('ạ', 150); // multibyte: 150 chars, 450 bytes
        $user = User::factory()->create(['name' => $longName]);

        $this->actingAs($user)
            ->post('/experiences', [
                'title' => 'Bai chia se',
                'content' => 'Noi dung',
                'name' => $longName,
            ])
            ->assertSessionHasNoErrors();

        $experience = Experience::firstOrFail();
        $this->assertSame($longName, $experience->name);
        $this->assertSame(150, mb_strlen($experience->name));
    }

    public function test_owner_with_a_150_char_name_can_edit(): void
    {
        $longName = str_repeat('x', 150);
        $owner = User::factory()->create(['name' => $longName]);
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => $longName,
            'title' => 'Ban dau',
            'content' => 'Noi dung',
            'status' => 'approved',
        ]);

        // The edit form re-posts the stored name verbatim; that round-trip
        // used to fail with a zero-feedback redirect.
        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => $longName,
                'title' => 'Da sua',
                'content' => 'Noi dung',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Da sua', $experience->fresh()->title);
    }

    public function test_a_name_over_the_column_width_is_rejected_and_now_visible(): void
    {
        // The cap is still a cap (255 = varchar width), and the authed create
        // page must RENDER the rejection, not swallow it.
        $user = User::factory()->create();
        $tooLong = str_repeat('x', 256);

        $this->actingAs($user)
            ->post('/experiences', [
                'title' => 'Ok',
                'content' => 'Noi dung',
                'name' => $tooLong,
            ])
            ->assertSessionHasErrors('name');

        $html = $this->actingAs($user)->get('/experiences/create')->assertOk()->getContent();
        // The authed branch (not the guest-only one) carries the visible
        // block — d-block defeats Bootstrap's display:none base class. The
        // name field sits ABOVE the title field on this page, so anchor on
        // the form's first element, the hidden input.
        $this->assertMatchesRegularExpression(
            '/type="hidden" name="name".*?invalid-feedback d-block[^>]*>[^<]*Trường name/s',
            $html,
            'a name rejection must render visibly on the authed create page'
        );
        $this->assertSame(0, Experience::count());
    }

    public function test_edit_page_surfaces_a_name_rejection_too(): void
    {
        $owner = User::factory()->create();
        $experience = Experience::create([
            'user_id' => $owner->id,
            'name' => 'Na',
            'title' => 'Bai chia se',
            'content' => 'Noi dung',
            'status' => 'approved',
        ]);

        $this->actingAs($owner)
            ->put("/experiences/{$experience->id}", [
                'name' => str_repeat('y', 256),
                'title' => 'Ok',
                'content' => 'Noi dung',
            ])
            ->assertSessionHasErrors('name');

        $html = $this->actingAs($owner)->get("/experiences/{$experience->id}/edit")->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/value="Na".*?invalid-feedback d-block[^>]*>[^<]*Trường name/is',
            $html,
            'a name rejection must render visibly on the edit page'
        );
        // Nothing was written — the same guard that made the error visible
        // still protects the row.
        $this->assertSame('Bai chia se', $experience->fresh()->title);
    }
}
