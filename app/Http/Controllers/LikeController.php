<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    /**
     * Issue #43: the target must exist, but a ghost id must not 500 with
     * internals in the body; let ModelNotFoundException bubble and the
     * caller turns it into a clean 404.
     */
    private function resolveLikeable(string $type, int $id): Model
    {
        return match ($type) {
            'alert' => Alert::findOrFail($id),
            'experience' => Experience::findOrFail($id),
            'comment' => Comment::findOrFail($id),
        };
    }

    /**
     * Issue #104: the blades render the like button for any authenticated
     * viewer and show() 403s strangers on unapproved posts (#20), but these
     * endpoints resolved any existing id via bare findOrFail (#43) — a user
     * knowing a pending/rejected id could bump its count and spam the author
     * with LikePostNotification/LikeCommentNotification. Same
     * read-gated/write-open shape as #95, closed the same way: only approved
     * posts accept like-state writes. Comments carry no status of their own,
     * so the owning post governs, mirroring the reply gate in #96.
     */
    private function ensureLikeableIsApproved(Model $model): void
    {
        if ($model instanceof Comment) {
            $post = $model->alert_id
                ? Alert::find($model->alert_id)
                : Experience::find($model->experience_id);
            abort_unless($post && $post->status === 'approved', 403);

            return;
        }
        abort_unless($model->status === 'approved', 403);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:alert,experience,comment',
            'id' => 'required|integer',
        ]);
        $user = auth()->user();
        // Issue #129: likes notify the post/comment author, so they carry the
        // same email-verification gate as the alert/experience/comment stores.
        // The real callers send Accept: application/json and branch on a
        // redirect key (see destroy's 401 shape below and the fetch handlers
        // in public/js/alerts_show.js), so the JSON branch matches the
        // endpoint's own auth-state contract.
        if (! $user->hasVerifiedEmail()) {
            if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Bạn cần xác thực email để thích bài viết.'], 403);
            }

            return back()->with('error', 'Bạn cần xác thực email để thích bài viết.');
        }
        $type = $request->type;
        $id = $request->id;
        $model = $this->resolveLikeable($type, $id);
        $this->ensureLikeableIsApproved($model);

        // Issue #139: the exists() check, the insert and the notification
        // used to be three autocommitted statements, so a like request that
        // passed exists() while the target was being deleted in another
        // request landed its row *after* the target's deleting sweeps —
        // permanent ghost: morph pairs carry no FK to cascade the row (#57)
        // and like notifications reference the post only inside the JSON
        // payload (#121), and no scheduled janitor ever re-sweeps late
        // writes. Wrapped in a transaction, the insert path re-verifies the
        // target with a current read *after* inserting and backs out when it
        // vanished; the notification sits after that re-check, so backing
        // out means there is never a bell row to erase. (Documented
        // residual: a delete committed after the re-check but before this
        // transaction's commit still slips through — closing that fully
        // needs row locking in the three models' delete sweeps too.)
        $vanished = false;
        DB::transaction(function () use ($model, $user, $type, &$vanished) {
            $inserted = false;
            if (! $model->likes()->where('user_id', $user->id)->exists()) {
                $inserted = true;
                try {
                    $model->likes()->create(['user_id' => $user->id]);
                } catch (QueryException $e) {
                    // Issue #43: check-then-insert raced with a concurrent like
                    // and hit the unique index. The row exists either way — the
                    // loser of the race continues as if the insert succeeded so
                    // the count below stays correct.
                    if (! $this->isDuplicateKey($e)) {
                        throw $e;
                    }
                    $inserted = false;
                }
            }

            // Current read: lockForUpdate() forces MySQL's REPEATABLE READ to
            // return the latest committed version instead of this
            // transaction's snapshot (SQLite's grammar drops the lock clause,
            // where its write lock makes mid-transaction interleaving
            // impossible anyway). Covers the duplicate-key-loser row too:
            // the user's like on a dead target is erased whichever way it
            // arrived.
            $stillThere = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->exists();
            if (! $stillThere) {
                $model->likes()->where('user_id', $user->id)->delete();
                $vanished = true;

                return;
            }

            // Only the winner of the race notifies; the loser's like already
            // exists and was counted by whoever inserted first.
            // Issue #162: experiences.user_id is nullable and the #47 FK
            // migration deliberately KEEPS the legacy NULL-owner rows, so
            // `$model->user_id != $user->id` is TRUE for null (loose compare)
            // and the unguarded ->user->notify() dereferenced a null belongsTo
            // -> "Call to a member function notify() on null" -> 500, and
            // because this sits inside the #139 transaction the insert rolled
            // back too — legacy posts were permanently un-likable for every
            // user. The truthy-owner guard mirrors CommentController's #153
            // `$postOwnerId &&` gate: a falsy owner records the like cleanly
            // and attempts no notification.
            if ($inserted && $type === 'comment' && $model->user_id && $model->user_id != $user->id) {
                $post = $model->alert_id ? Alert::find($model->alert_id) : Experience::find($model->experience_id);
                $postType = $model->alert_id ? 'alert' : 'experience';
                $model->user->notify(new LikeCommentNotification($user, $model, $post, $postType));
            }
            if ($inserted && ($type === 'alert' || $type === 'experience') && $model->user_id && $model->user_id != $user->id) {
                $model->user->notify(new LikePostNotification($user, $model, $type));
            }
        });

        if ($vanished) {
            if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy bài viết.'], 404);
            }

            return back()->with('error', 'Bài viết không còn tồn tại.');
        }

        $count = $model->likes()->count();
        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }

        return back();
    }

    public function destroy(Request $request)
    {
        // Issue #43: validation ran inside the old catch-all, so a 422
        // became a 500 leaking $e->getMessage() to the client.
        $request->validate([
            'type' => 'required|in:alert,experience,comment',
            'id' => 'required|integer',
        ]);
        if (! auth()->check()) {
            return response()->json(['success' => false, 'redirect' => route('login')], 401);
        }
        $user = auth()->user();
        try {
            $model = $this->resolveLikeable($request->type, $request->id);
        } catch (ModelNotFoundException $e) {
            // Unliking something that never existed is a 404, not a 500 with
            // an exception body.
            return response()->json(['success' => false, 'message' => 'Không tìm thấy bài viết.'], 404);
        }
        $this->ensureLikeableIsApproved($model);
        $model->likes()->where('user_id', $user->id)->delete();
        $count = $model->likes()->count();

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * 23000 = integrity constraint violation; 1062 MySQL duplicate entry /
     * 19 unique-constraint SQLite. Narrow enough to never swallow a real
     * DB failure.
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '1062')
            || str_contains($e->getMessage(), 'UNIQUE constraint')
            || str_contains($e->getMessage(), 'SQLSTATE[23000]');
    }
}
