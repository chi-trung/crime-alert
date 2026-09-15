<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WantedPerson extends Model
{
    protected $table = 'wanted_people';

    protected $fillable = [
        'name',
        'birth_year',
        'address',
        'parents',
        'crime',
        'decision',
        'agency',
        'source_key',
    ];

    /**
     * Issue #305: the byte-exact identity digest every wanted-person lookup
     * keys on. sha1 over the JSON-encoded (#293) field tuple AS STORED
     * (#106-truncated), computed in PHP so no column collation can fold it —
     * MySQL's utf8mb4_unicode_ci compares 'Nguyễn Văn An' = 'Nguyễn Văn Ân'
     * TRUE, which silently undid #293's four-field key. json_encode (not a
     * raw join) so null, '' and absent stay distinguishable; 40 chars fits a
     * string(40) column without the #294 tail-dance.
     */
    public static function digestFor(?string $name, ?string $birthYear, ?string $address, ?string $decision): string
    {
        return sha1(json_encode([$name, $birthYear, $address, $decision]));
    }

    /**
     * Key existing rows that predate the digest column, chunked so a large
     * imported table cannot exhaust memory. Returns how many rows were
     * filled. Distinct stored bytes always produce distinct digests, so the
     * UNIQUE index the migration adds right after accepts the backfill —
     * EXCEPT genuine byte-identical duplicates (possible in hand-imported
     * data from before any index existed): the first of each group gets the
     * key, the rest stay NULL. UNIQUE tolerates multiple NULLs, and letting
     * duplicates collide into one digest would fail the index build outright
     * — silently picking one of them for the crawler instead would recreate
     * the #305 merge behind a new name.
     */
    public static function backfillMissingSourceKeys(): int
    {
        $filled = 0;

        static::whereNull('source_key')
            ->select(['id', 'name', 'birth_year', 'address', 'decision'])
            ->chunkById(500, function ($people) use (&$filled) {
                foreach ($people as $person) {
                    $key = static::digestFor(
                        $person->name,
                        $person->birth_year,
                        $person->address,
                        $person->decision,
                    );
                    if (DB::table('wanted_people')->where('source_key', $key)->where('id', '!=', $person->id)->exists()) {
                        continue;
                    }
                    DB::table('wanted_people')->where('id', $person->id)->update(['source_key' => $key]);
                    $filled++;
                }
            });

        return $filled;
    }
}
