<?php

namespace Tests\Feature;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #235: SupportRequestController::store()/sendMessage() bound subject
 * to max:255 and message to max:5000, but on the HTML path NOTHING rendered
 * the resulting $errors: layouts/app.blade.php flashes only success/error/
 * info, and support/create.blade.php had no @error block at all — so an
 * over-limit submit 302'd back to a form re-filled from old() with zero
 * visible trace of the rejection. The user believed the request was filed;
 * no support_requests row existed. Same silent-rejection class #224 fixed
 * for experiences (commit aaf664c). The support thread page's no-JS chat
 * form had the identical hole.
 *
 * These tests assert the rendered rejection, not the session bag:
 * SupportTest already pins that the validation REJECTS (assertSessionHas-
 * Errors). What was unpinned — and what regressed silently — is whether a
 * human can SEE that it did.
 */
class SupportSilentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlong_subject_is_visible_on_the_reloaded_create_form(): void
    {
        $user = User::factory()->create();

        // The withHeaders(Referer) makes validate()'s failure redirect land
        // back on the create form — the path a real browser walks.
        $this->withHeaders(['Referer' => route('support.create')])
            ->actingAs($user)->followingRedirects()->post('/support', [
                'subject' => str_repeat('x', 256),
                'message' => 'ok body',
            ])
            // No assertSessionHasErrors here: followingRedirects transfers
            // $errors into the view during the followed GET, which is exactly
            // the point '' '' the assertions below then prove visible.
            ->assertOk()
            ->assertSee('is-invalid', false)
            ->assertSee('invalid-feedback', false)
            // The validation message itself (vi: "Trường subject không được
            // lớn hơn 255 ký tự.") — assert only the invariant part; the
            // humanized attribute is Laravel's business, not this fix's.
            ->assertSee('255')
            ->assertSee(str_repeat('x', 256)); // old() re-fill still there
    }

    public function test_overlong_message_is_visible_on_the_reloaded_create_form(): void
    {
        $user = User::factory()->create();

        $this->withHeaders(['Referer' => route('support.create')])
            ->actingAs($user)->followingRedirects()->post('/support', [
                'subject' => 'fine',
                'message' => str_repeat('y', 5001),
            ])
            ->assertOk()
            ->assertSee('is-invalid', false)
            ->assertSee(str_repeat('y', 200)); // the re-filled rejected text
        $this->assertSame(0, SupportRequest::count());
    }

    public function test_chat_form_renders_the_rejected_overlong_message_in_place(): void
    {
        $owner = User::factory()->create();
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'Thread']);

        $this->withHeaders(['Referer' => route('support.show', $thread)])
            ->actingAs($owner)->followingRedirects()->post(route('support.sendMessage', $thread), [
                'message' => str_repeat('z', 5001),
            ])
            ->assertOk()
            ->assertSee('is-invalid', false)
            ->assertSee('invalid-feedback', false);
    }

    public function test_both_forms_cap_inputs_at_the_server_bounds(): void
    {
        // The client bound must equal the server bound: maxlength 255/5000
        // matches store()'s rules verbatim, so the browser truncates exactly
        // where the server would have rejected — the two paths can never
        // disagree on what "too long" means.
        $user = User::factory()->create();
        $owner = $user;
        $thread = SupportRequest::create(['user_id' => $owner->id, 'subject' => 'T']);

        $create = $this->actingAs($user)->get(route('support.create'))->assertOk();
        $create->assertSee('maxlength="255"', false);
        $create->assertSee('maxlength="5000"', false);

        $this->actingAs($owner)->get(route('support.show', $thread))
            ->assertOk()
            ->assertSee('maxlength="5000"', false);
    }

    public function test_a_valid_request_still_files_and_shows_no_error_markup(): void
    {
        // Guard against the fix over-firing: with $errors empty the is-
        // invalid/invalid-feedback markup must be absent and the row must
        // exist — the @error conditionals must not leak a permanent red
        // state onto the healthy form.
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()->post('/support', ['subject' => 'Normal', 'message' => 'Fine'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SupportRequest::count());
        $this->actingAs($user)->get(route('support.index'))->assertOk();
        $this->actingAs($user)->get(route('support.create'))
            ->assertOk()
            ->assertDontSee('is-invalid', false)
            ->assertDontSee('invalid-feedback', false);
    }
}
