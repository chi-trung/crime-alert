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
        // redirect key (public/js/alerts_show.js and siblings), and they are
        // signed-in users here — Authenticate's 401 (see bootstrap/app.php,
        // issue #207) handles guests — so the useful destination for THIS
        // failure is the verification notice, not /login: the client bounces
        // them to the page where they can actually fix it.
        if (! $user->hasVerifiedEmail()) {
            if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn cần xác thực email để thích bài viết.',
                    'redirect' => route('verification.notice'),
                ], 403);
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
        //
        // Issue #279: #139's current read checked only EXISTS, adopting the
        // existence half of the CommentController::store() #153 idiom but
        // not its status half. The approval gate sits at the top of store()
        // (L84, a plain find outside this transaction), so an admin reject
        // that commits between the gate and the re-check leaves the target
        // row ALIVE — only its status moved off 'approved' — and exists()
        // cannot see that. The like then commits as a permanent ghost on
        // hidden content (the same #57 morph pair nothing sweeps until the
        // post is deleted outright), the like count inflates for a post that
        // would resurface publicly on the next pending->approved, and the
        // notification below rings the author about engagement on content
        // moderation just rejected. Worse, the user cannot un-like it:
        // destroy() runs the same L84 gate and 403s. reject()/approve() are
        // plain conditional UPDATEs that clear neither likes nor bells, so
        // nothing else closes the window. The fix makes the current read
        // status-aware exactly like #153: for an Alert/Experience the locked
        // re-read's own status decides; for a Comment it is the owning post
        // that must still be approved (the type=comment case never re-read
        // the post in-transaction at all before #279). A demoted target backs
        // out with the SAME 403 the pre-race gate gives — the like row is
        // deleted and the transaction COMMITS that delete, so the abort is
        // raised only AFTER the closure returns (aborting inside would roll
        // the erase back and leave the ghost). The notification stays below
        // the check, so backing out means no bell row to chase (#139).
        $vanished = false;
        $demoted = false;
        DB::transaction(function () use ($model, $user, $type, &$vanished, &$demoted) {
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
            // arrived. #279: it is now a hydration (first()) not exists(), so
            // the row's CURRENT status — not just its presence — can veto the
            // like, mirroring #153's `$target->status !== 'approved'`.
            $live = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();
            if (! $live) {
                $model->likes()->where('user_id', $user->id)->delete();
                $vanished = true;

                return;
            }
            // The post a Comment's visibility inherits from (#96's rule, read
            // from the live comment's own pointers), lock-read alongside the
            // comment so a mid-flight reject of the owning post also backs
            // this out — pre-#279 the type=comment path never touched the
            // post's status inside the transaction at all.
            $post = null;
            if ($live instanceof Comment) {
                $post = $live->alert_id
                    ? Alert::whereKey($live->alert_id)->lockForUpdate()->first()
                    : Experience::whereKey($live->experience_id)->lockForUpdate()->first();
                $approved = $post && $post->status === 'approved';
            } else {
                $approved = $live->status === 'approved';
            }
            if (! $approved) {
                // The target lives but lost approval (or, for a comment, its
                // post did): erase the like, commit the erase, and answer the
                // same 403 the top-of-method gate gave pre-race.
                $model->likes()->where('user_id', $user->id)->delete();
                $demoted = true;

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
            // and attempts no notification. #279: notifications now use the
            // live models both re-read and re-verified above ($live, $post)
            // rather than a second non-locking fetch.
            if ($inserted && $type === 'comment' && $live->user_id && $live->user_id != $user->id) {
                $postType = $live->alert_id ? 'alert' : 'experience';
                $live->user->notify(new LikeCommentNotification($user, $live, $post, $postType));
            }
            if ($inserted && ($type === 'alert' || $type === 'experience') && $live->user_id && $live->user_id != $user->id) {
                $live->user->notify(new LikePostNotification($user, $live, $type));
            }
        });

        if ($vanished) {
            if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Không tìm thấy bài viết.'], 404);
            }

            return back()->with('error', 'Bài viết không còn tồn tại.');
        }

        if ($demoted) {
            // Raised after the transaction so the like-erase above is
            // committed, not rolled back. Same status the pre-race gate
            // returns: the content is (now) not likeable.
            abort(403);
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
        // Issue #207: a `! auth()->check()` 401-with-redirect branch used to
        // sit here, but both like routes live in the `auth` middleware group
        // (routes/web.php), so Authenticate stops a guest before this method
        // ever runs — the branch was unreachable dead code. The real contract
        // the fetch clients consume (data.redirect) is now produced centrally
        // by bootstrap/app.php's AuthenticationException renderer.
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
