<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewReplyOnComment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        if (! auth()->user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để bình luận hoặc trả lời bình luận.');
        }
        $request->validate([
            'content' => 'required|string|max:1000',
            // Issue #161: the three post-target fields gained 'integer' as
            // well as their exists rule. validateExists EXPLICITLY accepts
            // arrays (count(array_unique($value)) vs a getExistCount check),
            // so alert_id=[<validId>] validated clean and the raw array then
            // crashed downstream: at line ~57 it was copied into $data and
            // Comment::create inside the transaction threw "Array to string
            // conversion", and parent_id=[<validId>] reached findOrFail,
            // which returns a Collection for array input, whose ->alert_id
            // deref threw 'Property [id] does not exist on this collection
            // instance' — both 500s for any verified user. 'integer' rejects
            // the array before exists runs (no bail needed: exists on an
            // array is wasteful but harmless — the attribute is already
            // failed, and nothing downstream executes when validation
            // returns).
            // A top-level comment needs a post; a reply inherits one from
            // its parent (checked below), so only requires the field when
            // neither sibling is present.
            'alert_id' => 'nullable|required_without_all:experience_id,parent_id|integer|exists:alerts,id',
            'experience_id' => 'nullable|integer|exists:experiences,id',
            // Issue #35: parent used to be stored unchecked — no existence
            // rule, and nothing bound it to the submitted post.
            'parent_id' => 'nullable|integer|exists:comments,id',
        ]);
        $data = [
            'user_id' => auth()->id(),
            'content' => $request->content,
        ];
        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = Comment::findOrFail($request->parent_id);
            // A reply hangs off its parent thread, so the parent defines the
            // owning post. The client's own post id may only agree with it;
            // anything else is a crafted cross-thread injection attempt.
            if (($request->filled('alert_id') && (int) $request->alert_id !== (int) $parent->alert_id)
                || ($request->filled('experience_id') && (int) $request->experience_id !== (int) $parent->experience_id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Bình luận cha không thuộc bài viết này.',
                ]);
            }
            $data['parent_id'] = $parent->id;
            if ($parent->alert_id) {
                $data['alert_id'] = $parent->alert_id;
            }
            if ($parent->experience_id) {
                $data['experience_id'] = $parent->experience_id;
            }
        } elseif ($request->filled('alert_id')) {
            $data['alert_id'] = $request->alert_id;
        } else {
            $data['experience_id'] = $request->experience_id;
        }
        // Issue #95: the blades only offer the form on approved posts and
        // show() 403s strangers elsewhere (#20), but this endpoint accepted
        // comments on pending/rejected posts — a verified user could spam
        // an author's feed through a rejected post. Replies inherit their
        // parent's post above, so checking the resolved target covers both
        // branches with one gate.
        // Issue #153: that #95 check, the Comment::create, and the notify
        // used to be separate autocommitted statements, so a request that
        // passed the check while its target (or parent) was deleted
        // mid-flight landed a row the alert_id/experience_id/parent_id FKs
        // then rejected (MySQL 1452 / SQLite "FOREIGN KEY constraint
        // failed" -> 500), re-fetched a now-null $post and dereferenced it
        // in the reply notify (NewReplyOnComment::toArray reads $post->id
        // -> 500), or rang a bell at the author of a deleted thread that
        // the post's own deleting sweeps had already finished. Same shape
        // as #139 (likes); fixed the same way — the whole create+notify
        // sequence runs in one transaction, the #95 target gate moves to a
        // current read inside it, and the notifications use those
        // re-verified models instead of a second fetch, so a raced delete
        // backs out with exactly the response a pre-existing delete gets
        // (403 for the post, 404 for the parent — the endpoint's own
        // abort_unless/findOrFail at the top) and no bell row is ever
        // written. (Documented residual, inherited from #139: a delete
        // committed after the post-insert re-check but before this
        // transaction's commit still slips through — closing that fully
        // needs row locking in the three models' delete sweeps too.)
        $currentUserId = auth()->id();
        $vanished = null;
        $comment = DB::transaction(function () use ($data, $parent, $currentUserId, &$vanished) {
            // Authoritative re-check of the owning target as the
            // transaction's first statement: lockForUpdate() forces MySQL's
            // REPEATABLE READ to return the latest committed version instead
            // of this transaction's snapshot (SQLite's grammar drops the
            // lock clause, where its write lock makes mid-transaction
            // interleaving impossible anyway). A vanished/unapproved target
            // backs out to the same 403 the #95 gate gives pre-race.
            $target = isset($data['alert_id'])
                ? Alert::whereKey($data['alert_id'])->lockForUpdate()->first()
                : Experience::whereKey($data['experience_id'])->lockForUpdate()->first();
            if (! $target || $target->status !== 'approved') {
                $vanished = 'target';

                return null;
            }
            // A reply inherits its post from the parent, so the parent must
            // be current too; backing out to 404 mirrors findOrFail's
            // answer to an already-missing parent.
            if ($parent && ! Comment::whereKey($parent->id)->lockForUpdate()->exists()) {
                $vanished = 'parent';

                return null;
            }
            try {
                $created = Comment::create($data);
            } catch (QueryException $e) {
                if (! $this->isForeignKeyViolation($e)) {
                    throw $e;
                }
                // The insert raced a delete that landed after the re-checks
                // and the FK rejected the row. Name the vanished party with
                // the same current reads the non-throwing path uses, so the
                // response matches what an already-deleted target gets
                // (the users FK is unreachable — the auth user is alive
                // past the middleware and this endpoint's own gate).
                $targetStillThere = $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->exists();
                $vanished = $targetStillThere ? 'parent' : 'target';

                return null;
            }
            // Post-insert current read: covers a delete that lands between
            // the re-check and the insert on a backend whose FKs are off,
            // and the after-insert hook path where the row wrote fine but
            // the post or parent then vanished underneath it. Erase the
            // comment (a no-op where an FK cascade already dropped it) and
            // back out *before* any notification fires — backing out here
            // means there is never a bell row to chase.
            $postStillThere = $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->exists();
            $parentStillThere = ! $parent || Comment::whereKey($parent->id)->lockForUpdate()->exists();
            if (! $postStillThere || ! $parentStillThere) {
                Comment::whereKey($created->id)->delete();
                $vanished = $postStillThere ? 'parent' : 'target';

                return null;
            }
            // Gửi notification hợp lý. $target/$parent are the live models
            // both re-read and re-verified inside this transaction — passing
            // them straight through replaces the old second fetch of $post,
            // which is what let a raced delete hand NewReplyOnComment a null
            // post to deref.
            $postType = $target instanceof Alert ? 'alert' : 'experience';
            $postOwnerId = $target->user_id;
            $parentOwnerId = $parent ? $parent->user_id : null;
            // Nếu là reply, chỉ gửi cho chủ comment cha (nếu khác người gửi)
            if ($parent && $parentOwnerId && $parentOwnerId != $currentUserId) {
                $parent->user->notify(new NewReplyOnComment($created, $parent, $target, $postType));
            } elseif ($postOwnerId && $postOwnerId != $currentUserId) {
                // Nếu là bình luận gốc, chỉ gửi cho chủ bài viết (nếu khác người gửi)
                $target->user->notify(new NewCommentOnPost($created, $target, $postType));
            }

            return $created;
        });
        if ($vanished === 'target') {
            abort(403);
        }
        if ($vanished === 'parent') {
            abort(404);
        }
        if ($comment->experience_id) {
            return redirect()->route('experiences.show', $comment->experience_id)->with('success', 'Bình luận đã được gửi!');
        }

        return back()->with('success', 'Bình luận đã được gửi!');
    }

    /**
     * 1452 = MySQL foreign-key child-row rejection; "FOREIGN KEY constraint
     * failed" = the SQLite message (this repo's connection enforces FKs by
     * default — config/database.php). Narrow enough to rethrow any other DB
     * failure rather than swallow a real bug.
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '1452')
            || str_contains($e->getMessage(), 'FOREIGN KEY constraint failed');
    }

    public function edit(Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
            abort(403);
        }

        return view('comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
    {
        // Issue #237: same verified-email invariant as store() above — an
        // account that un-verified itself via PATCH /profile must not keep
        // write rights over published content.
        if (! auth()->user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để chỉnh sửa bình luận.');
        }
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
            abort(403);
        }
        $request->validate(['content' => 'required|string|max:1000']);
        $comment->update(['content' => $request->content]);
        if ($comment->experience_id) {
            return redirect()->route('experiences.show', $comment->experience_id)->with('success', 'Cập nhật bình luận thành công!');
        }

        return redirect()->route('alerts.show', $comment->alert_id)->with('success', 'Cập nhật bình luận thành công!');
    }

    public function destroy(Comment $comment)
    {
        // Issue #237: same gate as update().
        if (! auth()->user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để xóa bình luận.');
        }
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
            abort(403);
        }
        $comment->delete();

        return back()->with('success', 'Đã xóa bình luận!');
    }
}
