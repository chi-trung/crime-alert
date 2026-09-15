<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #303 (r14 redirect-flow): WantedListController::index is the ONLY
 * public GET route that calls $request->validate(). When the #145 array
 * payload (?q[]=a) fails the string rule, the framework renders the
 * failure as redirect($exception->redirectTo ?? url()->previous())
 * (Handler::invalid, L782) — and UrlGenerator::previous() PREFERS the raw
 * Referer header: to() passes any isValidUrl() string (the regex
 * ~^(#|//|https?://|...)~ at UrlGenerator L684) through verbatim. So a lure
 * link on an attacker page (top-level navigation, no CSRF needed on GET)
 * plus Referer: https://evil.example/ turns a crime-alert URL into a 302
 * straight onto evil.com: open redirect / phishing bounce from a trusted
 * origin. The app already gates exactly this trust via LocalUrl (#110 /
 * #245 / #254) for notification redirect targets and rendered back-links —
 * this framework-automatic path never consults it. POST validate() sinks
 * elsewhere stay CSRF-gated, so this is the one cross-site-reachable
 * instance; sweep test below pins that fact.
 */
class WantedListValidationRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function failedSearch(string $referer): string
    {
        // q goes in the URI itself: TestCase::get()'s second argument is
        // $content (body), not query params — passing the array there sends
        // no q at all and validation passes (200).
        return $this->withHeaders(['Referer' => $referer])
            ->get('/wanted-list?q%5B%5D=a')
            ->assertStatus(302)
            ->headers->get('Location');
    }

    public function test_external_referer_is_not_used_as_the_failure_redirect(): void
    {
        $location = $this->failedSearch('https://evil.example/phishing');

        $this->assertStringNotContainsString(
            'evil.example',
            $location,
            'Handler::invalid falls back to url()->previous(), which echoes the raw Referer through to() verbatim (isValidUrl passes absolute https:// URLs untouched) — a lure link on evil.example plus ?q[]=a turns a trusted crime-alert URL into a 302 onto the attacker: open redirect reachable by any guest, no CSRF token needed on GET'
        );
        $this->assertSame(route('wanted_list.index'), $location, 'local failure redirects must fall back to the wanted-list itself');
    }

    public function test_protocol_relative_referer_is_not_used_as_the_failure_redirect(): void
    {
        // //evil.example/x passes UrlGenerator::to()'s isValidUrl() untouched
        // (browsers resolve it against the current scheme) and must not
        // become the Location.
        $location = $this->failedSearch('//evil.example/x');

        $this->assertStringNotContainsString('evil.example', $location);
        $this->assertSame(route('wanted_list.index'), $location);
    }

    public function test_local_referer_still_rounds_the_visitor_back(): void
    {
        // UX control: the gate must keep honest navigation intact — a
        // same-site Referer (the search page itself) returns there with
        // input flashed, not to a bare list page.
        $location = $this->failedSearch('https://'.parse_url(config('app.url'), PHP_URL_HOST).'/wanted-list?q=an');

        $this->assertStringContainsString('/wanted-list?q=an', $location);
        $this->assertStringNotContainsString('evil.example', $location);
    }

    public function test_the_failure_redirect_is_gated_by_localurl(): void
    {
        // Wiring pin: the fix must route the validation-failure redirect
        // through the shared LocalUrl gate (the #110/#245/#254 rule) rather
        // than hand-rolling a second same-host comparison that can drift.
        $source = \file_get_contents(base_path('app/Http/Controllers/WantedListController.php'));

        $this->assertMatchesRegularExpression(
            '/ValidationException.*redirectTo/s',
            $source,
            'the validation failure still escapes through the framework default redirect(url()->previous()), which echoes the raw Referer into the 302 Location'
        );
        $this->assertMatchesRegularExpression(
            '/LocalUrl::previousOr/',
            $source,
            'failure redirect target is not gated by the shared LocalUrl rule (#110/#254) — any bespoke host comparison here can drift from the canonical one'
        );
    }

    public function test_wanted_list_is_the_only_public_get_validate_sink(): void
    {
        // Sweep pin (honest scoping of #303): every other controller
        // validate() call must sit behind auth (or CSRF-gated POST). If a
        // future public GET adds validate(), this test fails and the author
        // must gate its failure redirect like WantedListController now does.
        $public = [];
        foreach (\Route::getRoutes() as $route) {
            if (! \in_array('GET', $route->methods(), true)) {
                continue;
            }
            $controller = $route->getAction('controller');
            if (! \is_string($controller) || ! \str_contains($controller, '@')) {
                continue;
            }
            $mw = $route->gatherMiddleware();
            $isPublic = ! collect($mw)->contains(fn ($m) => \is_string($m) && str_starts_with($m, 'auth'));
            if (! $isPublic) {
                continue;
            }
            [$class, $method] = explode('@', $controller);
            $rm = new \ReflectionMethod($class, $method);
            $src = implode('', \array_slice(
                \file($rm->getFileName()),
                $rm->getStartLine() - 1,
                $rm->getEndLine() - $rm->getStartLine() + 1
            ));
            if (str_contains($src, '->validate(')) {
                $public[] = $route->uri().' -> '.$controller;
            }
        }

        $this->assertSame(
            ['wanted-list -> App\Http\Controllers\WantedListController@index'],
            $public,
            'public-GET validate() sinks changed shape — each one re-exposes the raw-Referer failure redirect until gated: '.implode('; ', $public)
        );
    }
}
