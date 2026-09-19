<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #351: three client-side defects, two of which a static-source pin can
 * catch and one that needs the rendered page:
 *
 *  - login.js and the inline <script> in auth/login.blade.php defined the SAME
 *    password-toggle listener, so one click flipped the field twice and the
 *    eye never opened;
 *  - dead code that only ever existed inside HTML comments or unwired CSS
 *    (togglePassword, .tooltip, .input-icon on the register page);
 *  - console.log('Raw response:', text) echoing the raw server body into a
 *    visitor's console on both like buttons.
 */
class ClientScriptHygieneTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_wires_exactly_one_password_toggle(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // The external file is gone; the inline copy survives. Assert the tag,
        // not the bare string: the issue-#351 comments legitimately mention
        // the deleted filename.
        $this->assertStringNotContainsString(
            'src="'.asset('js/login.js').'"',
            $html,
            'the duplicated login.js must not be loaded'
        );
        $this->assertStringContainsString('passwordToggle', $html, 'the surviving toggle must still be wired');
    }

    public function test_login_toggle_handler_appears_once(): void
    {
        // The bug was the handler existing twice. Count the addEventListener
        // registration, not the element: the id appears in markup and in JS.
        $html = $this->get('/login')->assertOk()->getContent();

        $count = substr_count($html, "getElementById('passwordToggle')");
        $this->assertSame(1, $count, "the toggle lookup must happen exactly once, found {$count}");
    }

    public function test_no_dead_toggle_password_function_ships(): void
    {
        // register.js carried togglePassword() from its first commit while
        // both call sites sat inside HTML comments. A grep for the definition
        // catches its return.
        $js = file_get_contents(public_path('js/register.js'));
        $this->assertStringNotContainsString('function togglePassword', $js, 'the unwired function must be gone');
        $this->assertStringContainsString('checkPasswordStrength', $js, 'the live strength checker must survive');
    }

    public function test_dead_css_selectors_are_not_carried_on_the_register_page(): void
    {
        $css = file_get_contents(public_path('css/register.css'));

        // .input-icon: all four wrapper divs on the register page are
        // commented out, and the .form-input padding that made room for the
        // glyph with it. Match the selector as a rule head, not a substring —
        // the #351 comment that documents the removal names the class too.
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\.input-icon\s*\{/m',
            $css,
            'the dead .input-icon rule must be gone'
        );
        $this->assertStringNotContainsString('padding-left: 45px', $css);

        // .tooltip/.tooltiptext: no element carries the class and no JS
        // assigns it.
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\.tooltip(\s|\.|\{|,)/m',
            $css,
            'the dead .tooltip rules must be gone'
        );

        // The rules the page actually needs must survive the sweep.
        $this->assertStringContainsString('.password-requirements', $css);
        $this->assertStringContainsString('.requirement', $css);
        // .password-strength used to be listed here as a live rule, but its
        // div rendered blank on every keystroke: checkPasswordStrength() only
        // flips the .requirement rows and never writes #passwordStrength. Both
        // the rule and the div are removed in #370.
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*\.password-strength\s*\{/m',
            $css,
            'the unreachable strength-bar rule must be gone'
        );
    }

    public function test_no_raw_server_body_is_echoed_to_the_visitor_console(): void
    {
        // The two like buttons logged the raw response text — which can carry
        // a rendered exception, user content or token noise — into a visitor's
        // console. assert() notices quoting fixed strings are fine.
        foreach (['alerts_show.js', 'experiences_show.js'] as $file) {
            $js = $this->withoutComments(file_get_contents(public_path('js/'.$file)));

            $this->assertStringNotContainsString(
                'console.log(',
                $js,
                "{$file} must not write to the visitor's console"
            );
            $this->assertStringNotContainsString(
                'console.error(',
                $js,
                "{$file} must not write to the visitor's console"
            );

            // Positive control so the negative pins above cannot pass by the
            // error path being deleted entirely. #414 replaced the raw
            // alert() with a live region, so the guarantee that is actually
            // being pinned is that a failure is still reported somewhere the
            // user can perceive — not the specific string it once used.
            $js = $this->withoutComments(file_get_contents(public_path('js/'.$file)));
            $this->assertStringContainsString('announceLikeFailure(', $js, "{$file} must still surface failures to the user");
        }
    }

    public function test_share_popup_handler_is_not_defined_twice(): void
    {
        // alerts/show.blade.php used to define toggleSharePopupAlert inline
        // AND in alerts_show.js. Function redefinition is silent: the second
        // wins and the first becomes dead weight, but a reader cannot tell
        // which is live. #398 then moved the whole behaviour into
        // public/js/share_popup.js, which both show pages load — so the
        // handler no longer exists at all in the page-specific files.
        $alert = $this->approvedAlert();

        $html = $this->actingAs($alert->user)
            ->get("/alerts/{$alert->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            'function toggleSharePopupAlert',
            $html,
            'the blade must not redefine the handler inline'
        );
        $this->assertStringNotContainsString(
            'function closeSharePopupAlert',
            $html,
            'the blade must not redefine the closer inline'
        );
        $this->assertSame(
            1,
            substr_count($html, 'src="'.asset('js/alerts_show.js').'"'),
            'the page-specific file must still load exactly once'
        );
        $this->assertSame(
            1,
            substr_count($html, 'src="'.asset('js/share_popup.js').'"'),
            'the shared popup helper must load exactly once'
        );

        // The behaviour lives once, in the shared helper, and neither page
        // file keeps its own copy of the toggle.
        foreach (['alerts_show.js', 'experiences_show.js'] as $file) {
            $this->assertSame(
                0,
                substr_count(file_get_contents(public_path('js/'.$file)), 'function toggleSharePopup'),
                "{$file} must delegate the popup to the shared helper"
            );
        }
        $this->assertSame(
            1,
            substr_count(file_get_contents(public_path('js/share_popup.js')), 'function initSharePopup'),
            'the shared helper defines the wiring exactly once'
        );
    }

    public function test_welcome_stat_animation_cannot_render_nan(): void
    {
        // animateStats() gated on text.includes('+'), which a non-numeric
        // string containing a plus also satisfies; parseInt then yields NaN
        // and the stat displays "NaN+". The guard now requires an integer.
        $js = file_get_contents(public_path('js/welcome.js'));

        $this->assertStringContainsString('Number.isInteger', $js, 'the count guard must be numeric');

        // The welcome page's own stat rows: "24/7" must NOT be treated as a
        // count, the two real counts must remain.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('>24/7<', $html, 'the non-numeric stat must render verbatim');
        $this->assertStringContainsString('>1000+<', $html);
        $this->assertStringContainsString('>50+<', $html);
    }

    /**
     * Strip // line comments so an explanatory comment cannot satisfy a
     * negative assertion by quoting the removed code verbatim.
     */
    private function withoutComments(string $code): string
    {
        return preg_replace('/^[ \t]*\/\/.*$/m', '', $code);
    }

    private function approvedAlert()
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->forceFill([
            'email_verified_at' => now(),
        ])->save());

        return Alert::create([
            'user_id' => $user->id,
            'title' => 'Cướp giật tại bến xe',
            'description' => 'Mô tả sự việc.',
            'type' => 'Cướp giật',
            'status' => 'approved',
            'location' => 'Bến xe Miền Đông',
            'latitude' => 10.80,
            'longitude' => 106.70,
        ]);
    }
}
