<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

/**
 * Shared same-host gate for URLs that originate from an untrusted source
 * (a stored notification payload, a raw Referer header). Extracted from
 * NotificationController::isLocalUrl (#110) so the redirect() target and the
 * rendered href now consult ONE rule instead of two that can drift.
 *
 * A relative path beginning with a single '/' is local by construction; an
 * absolute URL must match the app host, and protocol-relative //evil forms
 * fail the host comparison rather than inheriting the request scheme.
 */
class LocalUrl
{
    public static function isLocal(string $url): bool
    {
        // Issue #254: WHATWG URL parsing treats a backslash as a slash for
        // special schemes (http/https), so forms like /\evil.example — which
        // the naive one-slash test below used to wave through as "a relative
        // path" — actually enter the authority state in browsers and resolve
        // to https://evil.example. Normalize first, then demand that the
        // ORIGINAL string led with a real '/': that pairing rejects '/\' and
        // '//' alike (both normalize to a double separator) without letting
        // a bare '\evil.example' steal the path branch's trust after
        // normalization. Ordinary single-slash paths are untouched; the host
        // comparison then runs on the normalized form, matching what a
        // browser would actually fetch (http:\\localhost is the same host).
        $normalized = str_replace('\\', '/', $url);
        if ($url !== '' && $url[0] === '/' && ! str_starts_with($normalized, '//')) {
            return true;
        }
        $host = parse_url($normalized, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        return mb_strtolower($host) === mb_strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
    }

    /**
     * The URL the visitor came from, but only when it points back at this app;
     * anything else — an external absolute URL, a protocol-relative //evil
     * host, a javascript: pseudo-URL, or an empty value — falls back.
     *
     * The raw Referer header is checked BEFORE UrlGenerator::previous() gets
     * it: previous() feeds non-URL strings through to(), which prefixes them
     * with the app URL, so a hand-crafted Referer like javascript:alert(...)
     * would re-enter isLocal() looking like a same-host path. Checking the
     * header verbatim closes that laundering; when no header is sent, the
     * framework's session-settles to the URL the session actually recorded
     * (written from our own responses), which previous() resolves safely.
     *
     * This closes the href-render half of #110's trust model: the 302
     * back() path stays gated by CSRF + SameSite, but a rendered anchor is
     * not, so an attacker-placed link to /alerts/create could otherwise put
     * a live external "Quay lại" button on a trusted authenticated page.
     *
     * @param  string  $fallback  a same-app URL to use when the previous URL is not ours
     */
    public static function previousOr(string $fallback): string
    {
        $raw = request()->headers->get('referer');
        if (is_string($raw) && $raw !== '') {
            return self::isLocal($raw) ? $raw : $fallback;
        }

        $previous = app(UrlGenerator::class)->previous();

        return $previous !== '' && self::isLocal($previous) ? $previous : $fallback;
    }
}
