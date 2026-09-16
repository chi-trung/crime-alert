<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

/**
 * Issue #317: the framework's default current-page resolver only rejects a
 * value that fails FILTER_VALIDATE_INT or casts below 1 — every integer that
 * FITS in int64 slips through, including 9223372036854775800. That page then
 * reaches LengthAwarePaginator::firstItem(), which computes
 * ($page - 1) * $perPage + 1: the multiply overflows int64 to a float, and
 * Blade's echo prints "1.844674407371E+20" straight into a data cell (the
 * /wanted-list STT column) or the "Hiển thị X đến Y trong N kết quả" footer
 * (/admin/alerts). Independently, an int page ABOVE the last real page (say
 * ?page=20 with 3 rows) runs a huge-OFFSET SELECT that returns no rows on
 * MySQL — while total() is still 3, so the #227 footer guard (total() > 0)
 * passes and renders two blank spans: "Hiển thị  đến  trong 3 kết quả".
 *
 * The defect lives in page resolution, not in any view, so a per-view guard
 * would only cover the two footers that happen to call firstItem()/lastItem()
 * and leave the other nine ?page URLs able to fire the runaway OFFSET query.
 * This helper is the single choke point: resolve the page exactly as the
 * framework would, count the filtered total ONCE with the same
 * getCountForPagination() Builder::paginate() would call, clamp the page to
 * [1, lastPage], and hand paginate() both so it neither re-counts nor runs a
 * giant-offset SELECT. Passing $page and $total are native Builder::paginate
 * parameters, so this adds zero queries over a bare paginate() call.
 */
class BoundedPaginator
{
    /**
     * Length-aware paginate with the current page clamped to the real range.
     *
     * @param  Builder  $query  an Eloquent query builder (a relation reaches
     *                          this after any ->where()/->orderBy(), which
     *                          forward to the underlying Builder)
     * @param  string  $pageName  the ?page query key ("page", "alerts_page", ...)
     */
    public static function paginate(Builder $query, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        // One COUNT over the filtered set — the same query Builder::paginate()
        // would run, so the request still costs exactly one count.
        $total = $query->toBase()->getCountForPagination();

        // ceil over ints can only be huge if total is; 1 guards the empty set
        // so an unpopulated list stays on page 1 instead of a 0-offset oddity.
        $lastPage = max(1, (int) ceil($total / $perPage));

        // Resolve via the framework's own resolver (identical source the bare
        // paginate() uses), then clamp. A page below 1 is already forced to 1
        // by the resolver; the upper clamp is what kills both the int-overflow
        // float and the runaway OFFSET.
        $page = min(max(1, Paginator::resolveCurrentPage($pageName)), $lastPage);

        return $query->paginate($perPage, ['*'], $pageName, $page, $total);
    }
}
