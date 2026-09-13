<?php

namespace App\Http\Controllers;

use App\Models\WantedPerson;
use Illuminate\Http\Request;

class WantedListController extends Controller
{
    public function index(Request $request)
    {
        // Issue #145: ?q[]=a passes filled() (non-empty array), reaches the
        // #51 str_replace (array in -> array out) and the first '.' concat
        // throws 'Array to string conversion' — a public-route 500. Pin q to
        // a string so crafted arrays 302/422 instead.
        $request->validate(['q' => 'nullable|string']);
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
