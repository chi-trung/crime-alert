<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\QueryException;

/**
 * Shared predicate for the check-then-act hardening idiom (#43): a route
 * validates uniqueness with a SELECT, then writes; a concurrent insert can
 * land in the window and hit the DB UNIQUE index, raising a driver-specific
 * QueryException. Catching it narrowly and converting to the validation
 * error the serial path already produced is how #43 (likes), #153 and #191
 * close those races. The message substrings below are the ones Laravel's
 * query builders surface on both CI engines.
 */
trait GuardsUniqueRaces
{
    /**
     * 23000 = integrity constraint violation; 1062 MySQL duplicate entry /
     * 19 unique-constraint SQLite. Narrow enough to never swallow a real
     * DB failure. Mirrors LikeController::isDuplicateKey (#43).
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '1062')
            || str_contains($e->getMessage(), 'UNIQUE constraint')
            || str_contains($e->getMessage(), 'SQLSTATE[23000]');
    }
}
