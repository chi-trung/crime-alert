<?php

namespace App\Http\Controllers;

use App\Models\WantedPerson;
use App\Support\LocalUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WantedListController extends Controller
{
    public function index(Request $request)
    {
        // Issue #145: ?q[]=a passes filled() (non-empty array), reaches the
        // #51 str_replace (array in -> array out) and the first '.' concat
        // throws 'Array to string conversion' — a public-route 500. Pin q to
        // a string so crafted arrays 302/422 instead.
        //
        // Issue #303: but the 302 itself was the hole. This is the only
        // PUBLIC GET validate() sink in the app (pinned by the sweep test),
        // and on failure Handler::invalid does redirect($exception->redirectTo
        // ?? url()->previous()) — previous() prefers the raw Referer header
        // and to() passes absolute https://evil and protocol-relative
        // //evil forms through verbatim. A lure link (top-level navigation,
        // GET needs no CSRF token) plus an attacker Referer therefore turned
        // a crime-alert URL into a 302 onto the attacker's site. Gate the
        // failure target through the shared LocalUrl rule (#110/#245/#254):
        // same-site Referer still round-trips; anything else falls back to
        // this list itself.
        try {
            $request->validate(['q' => 'nullable|string']);
        } catch (ValidationException $e) {
            throw $e->redirectTo(LocalUrl::previousOr(route('wanted_list.index')));
        }
        $query = WantedPerson::query();
        if ($request->filled('q')) {
            // Issue #51: the old code matched "$q" as a raw LIKE pattern, so
            // a search for "50%" or "_" silently matched unrelated names
            // (and "%" alone returned the whole list). Escape the LIKE
            // wildcards and declare ESCAPE '!' — portable to MySQL and
            // SQLite, unlike a backslash escape which MySQL also strips
            // from string literals.
            //
            // The five old patterns had the same first and last entry and a
            // no-op double where() nesting; this is the same whole-word
            // intent: exact, "X …" prefix, "… X …" inside, "… X" suffix.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $request->q);
            $query->where(function ($sub) use ($escaped) {
                $sub->whereRaw("name LIKE ? ESCAPE '!'", [$escaped])
                    ->orWhereRaw("name LIKE ? ESCAPE '!'", [$escaped.' %'])
                    ->orWhereRaw("name LIKE ? ESCAPE '!'", ['% '.$escaped.' %'])
                    ->orWhereRaw("name LIKE ? ESCAPE '!'", ['% '.$escaped]);
            });
        }
        $wantedPeople = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('wanted_list.index', compact('wantedPeople'));
    }
}
