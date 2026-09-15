<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SupportRequestController extends Controller
{
    /**
     * Only administrators may run the admin queue actions. The routes
     * already sit behind the ['auth','admin'] group (AdminMiddleware 403s
     * non-admins end-to-end — issue #97's HTTP probe passes with or without
     * this), but close()/destroy() mutate state and must not depend on route
     * wiring alone: every sibling mutating action in this app carries its
     * own check. abort_unless keeps the no-session CLI shape out — an
     * unauthenticated direct call 403s the same as a signed-in non-admin.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin, 403);
    }

    /**
     * Only the thread owner and administrators may view/participate.
     */
    private function authorizeViewer(SupportRequest $supportRequest): void
    {
        abort_unless(
            Auth::check() && (Auth::user()->isAdmin || $supportRequest->user_id === Auth::id()),
            403
        );
    }

    // Danh sách yêu cầu của user
    public function index()
    {
        // Issue #75: unbounded ->get() of every request the user ever filed;
        // the id tiebreak keeps page boundaries stable when requests share a
        // created_at second (latest() alone orders by created_at only).
        $requests = SupportRequest::where('user_id', Auth::id())
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('support.index', compact('requests'));
    }

    // Form gửi yêu cầu mới
    public function create()
    {
        return view('support.create');
    }

    // Lưu yêu cầu mới
    public function store(Request $request)
    {
        // Issue #129: same gate as the alert/experience/comment stores —
        // this endpoint fans NewSupportRequest out to every admin with user
        // text in the payload, and unverified accounts (any fake email passes
        // registration) must not get the cheapest notification-spam primitive
        // in the app.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để liên hệ hỗ trợ.');
        }
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            // Issue #39: TEXT column, unbounded like #37 — bound it.
            'message' => 'required|string|max:5000',
        ]);
        // Issue #164: the thread and its opening message are one conceptual
        // act (show() renders the message list; a thread without its first
        // message is meaningless) but used to autocommit separately, so an
        // admin deleting the fresh thread from /admin/support between the two
        // inserts made the late SupportMessage::create an uncaught FK
        // violation (MySQL 1452 / SQLite "FOREIGN KEY constraint failed" ->
        // 500, submission lost), and any failure of the second insert left an
        // empty orphan thread in the queue (#53/#57 class). Same check-then-act
        // shape as #153/#139, fixed the same way: one DB::transaction, and the
        // back-out paths let their exception escape the closure so Laravel
        // rolls the transaction (thread row and any bells) back before the
        // outer catch answers with a flash instead of a 500 — a returned flag
        // would commit the half submission. On back-end semantics: MySQL's row
        // lock already blocks a concurrent DELETE until this transaction
        // commits (the sweep then cascades both rows), and SQLite's write lock
        // makes mid-transaction interleaving impossible — the catch and the
        // post-insert re-read cover FK-disabled backends and same-transaction
        // paths. A raced delete backs out with the error-flash shape the #129
        // gate above already uses, not a 500.
        $supportRequest = null;
        $vanished = false;
        try {
            DB::transaction(function () use ($data, &$supportRequest) {
                $supportRequest = SupportRequest::create([
                    'user_id' => Auth::id(),
                    'subject' => $data['subject'],
                ]);
                SupportMessage::create([
                    'support_request_id' => $supportRequest->id,
                    'user_id' => Auth::id(),
                    'message' => $data['message'],
                ]);
                // Post-insert current read: the thread may have been deleted
                // without tripping an FK (constraints off) — throw to roll the
                // rows back and back out *before* the fan-out so no bell
                // ever points at a dead thread.
                if (! SupportRequest::whereKey($supportRequest->id)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('support-request-vanished');
                }
                // Gửi notification cho admin
                // Issue #248: support routes are bare auth — an admin can
                // open a thread too — so this fan-out used to ring the
                // actor's own bell ("Người dùng <chính mình> đã mở yêu cầu
                // hỗ trợ"). The codebase's actor-exclusion contract, spelled
                // out at CommentController's reply fan-out ("chỉ gửi cho chủ
                // comment cha (nếu khác người gửi)"), applies: exclude the
                // opener from the recipient set.
                $admins = User::where('isAdmin', true)->where('id', '!=', Auth::id())->get();
                foreach ($admins as $admin) {
                    $admin->notify(new NewSupportRequest($supportRequest, Auth::user()));
                }
            });
        } catch (QueryException $e) {
            // Ordered before the RuntimeException arm: QueryException extends
            // it (PDOException's lineage), so an FK violation must not be
            // re-thrown by the sentinel guard below and sail past here.
            if (! $this->isForeignKeyViolation($e)) {
                throw $e;
            }
            // The opening message's FK rejected a thread that vanished
            // between the two inserts; the transaction (thread included) is
            // already rolled back by the escape.
            $vanished = true;
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'support-request-vanished') {
                throw $e;
            }
            $vanished = true;
        }
        if ($vanished) {
            return redirect()->back()->with('error', 'Yêu cầu không tồn tại, vui lòng thử lại.');
        }

        return redirect()->route('support.show', $supportRequest)->with('success', 'Đã gửi yêu cầu trợ giúp!');
    }

    /**
     * 1452 = MySQL foreign-key child-row rejection; "FOREIGN KEY constraint
     * failed" = the SQLite message (this repo's connection enforces FKs by
     * default — config/database.php). Narrow enough to rethrow any other DB
     * failure rather than swallow a real bug. (Same helper shape as
     * CommentController::isForeignKeyViolation from #153.)
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), '1452')
            || str_contains($e->getMessage(), 'FOREIGN KEY constraint failed');
    }

    // Xem chi tiết và nhắn tin
    public function show(SupportRequest $supportRequest)
    {
        $this->authorizeViewer($supportRequest);
        // Issue #89: id ASC tiebreak — chat order is oldest-first, and two
        // messages in the same second must not swap between renders (the
        // AJAX feed on this list is polled, so the flicker was live).
        // Issue #234: the thread can grow without bound and every row used
        // to be hydrated into the initial page (and re-sent on every 3s
        // poll) — read-unbounded class #67/#75 on the app's most frequent
        // background request. The view is now a latest-100 window; the rest
        // pages in through messagesAjax's before_id branch ("load older").
        $messages = $this->latestMessageWindow($supportRequest);
        $oldestShown = $messages->first()?->id;
        $hasMoreOlder = $oldestShown !== null
            && $supportRequest->messages()->where('id', '<', $oldestShown)->exists();

        return view('support.show', compact('supportRequest', 'messages', 'oldestShown', 'hasMoreOlder'));
    }

    /**
     * Issue #234: the newest $limit messages, oldest-first. The DESC twin of
     * show()'s #89 created_at+id tiebreak, so the window boundary is stable
     * across renders within the same-second ties the tiebreak exists for.
     */
    private function latestMessageWindow(SupportRequest $supportRequest, int $limit = 100)
    {
        return $supportRequest->messages()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    // Gửi tin nhắn mới
    public function sendMessage(Request $request, SupportRequest $supportRequest)
    {
        // Issue #129: replies ride NewSupportMessage to the counterpart (or
        // every admin) just like store() — same verification gate, checked
        // before authorizeViewer so the guard reads as the method's first
        // contract.
        // Issue #206: the /support/{id} live-chat form submits via fetch()
        // with Accept: application/json, but fetch() follows the 302 these
        // flash branches return transparently — the followed GET of the
        // thread comes back 200, so res.ok was TRUE even on a rejection.
        // The client then cleared the textarea as if the message had been
        // sent: the draft was lost, no error appeared, and the user had to
        // retype it. Under expectsJson() the rejections now answer with a
        // real 4xx JSON the client can detect; the HTML form-POST shape
        // (back()->with) stays untouched for non-JSON callers.
        if (! Auth::user()->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Bạn cần xác thực email để liên hệ hỗ trợ.'], 403);
            }

            return redirect()->back()->with('error', 'Bạn cần xác thực email để liên hệ hỗ trợ.');
        }
        $this->authorizeViewer($supportRequest);
        if ($supportRequest->status !== 'open') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.'], 409);
            }

            return back()->with('error', 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.');
        }
        $data = $request->validate([
            // Issue #39: same bound as store().
            'message' => 'required|string|max:5000',
        ]);
        // Issue #163: the gate above, the insert, and the notify used to be
        // separate autocommitted statements, so an admin closing the thread
        // between the route-binding snapshot and the insert landed a message
        // row and a full admin fan-out in a now-closed thread (defeating the
        // closed-thread invariant #98/#141 keep), and a destroy committed in
        // that window turned the insert into an uncaught FK violation (MySQL
        // 1452 / SQLite "FOREIGN KEY constraint failed" -> 500). A delete
        // landing after the row but before the notify rang bells whose
        // support_request_id payload pointed at a dead thread (the #102
        // orphan class). Same check-then-act shape as #153/#139 (and the
        // #164 fix above), closed the same way: one DB::transaction whose
        // current read re-runs the open-status gate on the authoritative row
        // (lockForUpdate forces MySQL's REPEATABLE READ to return the latest
        // committed version; SQLite's grammar drops the clause, where its
        // write lock already makes mid-transaction interleaving impossible),
        // a narrow FK catch, and a post-insert re-read that erases the row
        // and backs out *before* any notification fires. Back-out answers
        // mirror the endpoint's own pre-race responses: a vanished thread
        // gets the 404 the route binding would have produced, a raced close
        // gets the same closed-thread error flash as the snapshot gate.
        // (Documented residual inherited from #153: a close/commit landing
        // after the post-insert re-read but before this transaction commits
        // still slips through — closing that fully needs row locking in the
        // thread's own delete/close paths. Issue #271 closed the DELETE half
        // of that sentence: destroy() now takes the row lock before the #102
        // sweep and sweeps again at a fixed point after the delete; the
        // SQLite-only close window above remains as documented.)
        $outcome = DB::transaction(function () use ($supportRequest, $data) {
            $live = SupportRequest::whereKey($supportRequest->id)->lockForUpdate()->first();
            if (! $live) {
                return 'vanished';
            }
            if ($live->status !== 'open') {
                return 'closed';
            }
            try {
                $msg = SupportMessage::create([
                    'support_request_id' => $live->id,
                    'user_id' => Auth::id(),
                    'message' => $data['message'],
                ]);
            } catch (QueryException $e) {
                if (! $this->isForeignKeyViolation($e)) {
                    throw $e;
                }

                // The insert raced a delete that landed after the current
                // read and the FK rejected the row.
                return 'vanished';
            }
            // Post-insert current read: covers a delete that lands between
            // the re-read and the insert on a backend whose FKs are off (and
            // the created-hook window), where the row wrote fine but the
            // thread then vanished underneath it. Erase the message (a no-op
            // where an FK cascade already dropped it) and back out before any
            // bell is written. A close that lands in the same window is
            // backed out the same way — the thread's whole point is that a
            // closed thread takes no new messages.
            $after = SupportRequest::whereKey($live->id)->lockForUpdate()->first();
            if (! $after) {
                SupportMessage::whereKey($msg->id)->delete();

                return 'vanished';
            }
            if ($after->status !== 'open') {
                SupportMessage::whereKey($msg->id)->delete();

                return 'closed';
            }
            // Gửi notification cho đối phương. $after is the live, re-verified
            // thread — passing it through (instead of the binding snapshot the
            // old code used) means the bell payload can never describe a state
            // this transaction has already backed out of.
            $sender = Auth::user();
            if ($sender->isAdmin) {
                // Admin gửi, notify cho user
                // Issue #248: authorizeViewer lets an admin view and answer
                // his OWN thread, so $after->user can be the sender — the
                // bell then announced the sender's own message back to him.
                // Same actor-exclusion as the store() fan-out above (and
                // CommentController's reply rule).
                if ($after->user_id !== $sender->id) {
                    $after->user?->notify(new NewSupportMessage($after, $msg, $sender));
                }
            } else {
                // User gửi, notify cho admin (nếu có admin nào, hoặc notify cho tất cả admin)
                $admins = User::where('isAdmin', true)->get();
                foreach ($admins as $admin) {
                    $admin->notify(new NewSupportMessage($after, $msg, $sender));
                }
            }

            return null;
        });
        if ($outcome === 'vanished') {
            if ($request->expectsJson()) {
                // Same status the abort below produces, so the client can
                // branch on it; a 404 is a real !res.ok, so the draft is
                // preserved either way.
                return response()->json(['success' => false, 'message' => 'Yêu cầu không tồn tại.'], 404);
            }

            abort(404);
        }
        if ($outcome === 'closed') {
            // Issue #206: this raced close reached the same 302-plus-flash
            // shape as the snapshot gate above, so the JSON client counted
            // it as a successful send; it now mirrors that gate exactly.
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.'], 409);
            }

            return back()->with('error', 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    // Danh sách yêu cầu cho admin
    public function adminIndex()
    {
        // Issue #75: same class as #67/#71 — the whole request table loaded
        // on every admin page view. Paginate like every other list.
        $requests = SupportRequest::with('user')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return view('support.admin_index', compact('requests'));
    }

    // Đóng yêu cầu (admin)
    public function close(SupportRequest $supportRequest)
    {
        // Issue #97: defense in depth — the route group already 403s, but a
        // state-changing action must assert adminship itself.
        $this->authorizeAdmin();
        // Issue #98: closing an already-closed thread rewrote the same
        // status and reported success — a misleading no-op on every repeat
        // click. The sibling sendMessage() already treats closed as a
        // distinct state; close() now does too. The info bag is live
        // (layouts/app renders session('info')).
        //
        // Issue #256: the #98 guard read `status` off the in-memory route
        // binding, then wrote blindly — the last admin transition still
        // built on a snapshot check-then-act. A rival destroy() committed
        // inside the binding->UPDATE window touched 0 rows and still
        // flashed "Đã đóng yêu cầu!" for a thread that no longer exists,
        // and a second close racing the first was flashed success too.
        // The act is now the #189 conditional UPDATE keyed on
        // status='open' that approve()/reject() use, but each outcome is
        // confirmed against the authoritative row inside one transaction,
        // never off the affected-rows count alone (#233's doctrine): a
        // zero-match UPDATE needs the re-read anyway to say WHY it matched
        // nothing — a vanished thread gets the #247 honest 404, an
        // alive-closed one keeps the #98 info flash. The initial status
        // read is a SELECT, so the closed-thread path issues no UPDATE at
        // all — #98's literal query-log acceptance criterion survives the
        // rewrite; only the genuine open->closed transition writes.
        $outcome = DB::transaction(function () use ($supportRequest) {
            $live = SupportRequest::whereKey($supportRequest->id)->value('status');
            if ($live === null) {
                return null;
            }
            if ($live !== 'open') {
                return false;
            }
            // 'open'->'closed' always CHANGES the row, so on this transition
            // MySQL's CHANGED and SQLite's MATCHED agree: a non-zero count
            // proves THIS write closed the thread. A zero count means a
            // rival won (or deleted) inside the window — resolved next.
            $closed = SupportRequest::whereKey($supportRequest->id)
                ->where('status', 'open')
                ->update(['status' => 'closed', 'updated_at' => now()]);
            if ($closed) {
                return true;
            }

            // Issue #285: this is the SAME re-read #233 demands be
            // authoritative, and #163 says how on MySQL: a plain exists()
            // under REPEATABLE READ is a snapshot read, so a rival DELETE
            // (destroy(), admin or self) committed after the L403 status
            // read still sees its row here and flashes 'already closed'
            // for a thread that no longer exists. lockForUpdate turns it
            // into a current read — the doctrine this file already follows
            // at L114 (sendMessage), L267/L297 (store), and L464 (destroy)
            // (probe: snapshot exists() -> 1 / for-update -> 0 under two
            // real PDO connections on MySQL 8.4).
            return SupportRequest::whereKey($supportRequest->id)->lockForUpdate()->exists() ? false : null;
        });

        if ($outcome === null) {
            abort(404);
        }

        if (! $outcome) {
            return back()->with('info', 'Yêu cầu này đã được đóng trước đó.');
        }

        return back()->with('success', 'Đã đóng yêu cầu!');
    }

    // Xóa yêu cầu hỗ trợ (admin)
    public function destroy(SupportRequest $supportRequest)
    {
        // Issue #97: same in-method assertion as close() above.
        $this->authorizeAdmin();

        // Issue #271: this was a bare delete(), and the shape underneath it
        // matched what #266 closed for account deletion. The #102
        // notification sweep fires on the deleting() hook BEFORE the
        // DELETE acquires the thread row's X lock — so a sendMessage
        // transaction holding that lock (#163's lockForUpdate first
        // statement) still had its commit path ahead: the sweep found no
        // bell yet, the DELETE waited, and the fan-out then committed an
        // admin bell whose morph row has no FK to cascade (the whole
        // reason #102 exists). The thread row went away, so the hook could
        // never sweep it again — a permanent orphan whose url 404s and
        // which inflates the unread badge. Locking the row FIRST makes the
        // rival's own re-read see a dead thread and back out with the
        // honest 'vanished' answer instead, and the post-delete fixed-point
        // sweep catches any bell that still lands between the hook's
        // deletion and this transaction's commit. MySQL blocks the rival
        // for real; sqlite's compileLock is a no-op, where the sweep is
        // what actually closes the window. close() needs no twin: its
        // conditional open-only UPDATE already resolves inside the row
        // lock and bells only on a thread this path leaves alive.
        DB::transaction(function () use ($supportRequest): void {
            // Lock point: SELECT ... FOR UPDATE on the thread row, BEFORE
            // the deleting() sweep can be observed. pluck() so no model is
            // hydrated and no retrieved event can fire.
            SupportRequest::whereKey($supportRequest->id)->lockForUpdate()->pluck('id');

            $supportRequest->delete();

            // Fixed point AFTER the delete: anything the hook's window let
            // through (the rival bell above, or a retry re-inserting on a
            // backend without FK enforcement) is swept here — scoped to the
            // two classes this feature emits, with #102's comma-delimited
            // id matcher so thread N's sweep cannot eat thread N1's rows.
            // One statement clears every matching row, so the recheck only
            // has to prove the table stopped answering; same fixed-point
            // shape as #266's per-class sweeps.
            do {
                $swept = DB::table('notifications')
                    ->whereIn('type', [
                        NewSupportRequest::class,
                        NewSupportMessage::class,
                    ])
                    ->where('data', 'like', '%"support_request_id":'.$supportRequest->id.',%')
                    ->delete();
            } while ($swept > 0);
        });

        return back()->with('success', 'Đã xóa yêu cầu hỗ trợ!');
    }

    // API trả về danh sách tin nhắn dạng JSON
    public function messagesAjax(Request $request, SupportRequest $supportRequest)
    {
        $this->authorizeViewer($supportRequest);
        // Issue #89: id ASC tiebreak (see show()) — this is the polled feed.
        // Issue #234: this endpoint was GET by every open chat tab every 3
        // seconds with an uncapped ->get() and no throttle — 20 full-history
        // reads + payloads per minute per idle tab, forever. The feed is now
        // three bounded shapes:
        //   ?after_id=N  delta poll — only rows newer than N, LIMIT window.
        //                An idle tab pays one indexed
        //                WHERE support_request_id = ? AND id > ? ORDER BY id
        //                LIMIT 100 read that returns nothing.
        //   ?before_id=N "load older" — the $limit rows older than N, so the
        //                latest-100 window in show()/the no-param response
        //                doesn't silently truncate long threads.
        //   (no param) latest-100 window, same shape show() renders.
        // Route also got its own throttle lane (throttle:30,1,support-poll).
        $afterId = $request->integer('after_id');
        $beforeId = $request->integer('before_id');

        $query = $supportRequest->messages()->with('user');
        if ($afterId > 0) {
            $messages = $query->where('id', '>', $afterId)
                ->orderBy('created_at')->orderBy('id')
                ->limit(100)->get();
        } elseif ($beforeId > 0) {
            $messages = $query->where('id', '<', $beforeId)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(100)->get()->reverse()->values();
        } else {
            $messages = $this->latestMessageWindow($supportRequest);
        }

        $result = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'user' => $msg->user ? $msg->user->name : 'Ẩn danh',
                'is_me' => $msg->user_id == auth()->id(),
                'is_admin' => (bool) ($msg->user?->isAdmin ?? false),
                'content' => $msg->message,
                'created_at' => $msg->created_at->format('H:i d/m/Y'),
            ];
        });

        // The client tracks lastMessageId (Issue #234: it used to compare
        // COUNTs and discard identical-length responses, which forced the
        // full read); latest_id lets it advance even on an empty delta, and
        // oldest_id + has_more_older drive the "load older" affordance.
        $ids = $messages->pluck('id');

        return response()->json([
            'messages' => $result,
            // On an empty window echo the cursor back (or 0 when there was
            // no window), so the client never regresses its tracked id.
            'latest_id' => $ids->max() ?? (($afterId ?: $beforeId) + 0),
            'oldest_id' => $ids->min() ?? 0,
            'has_more_older' => $supportRequest->messages()
                ->where('id', '<', $ids->min() ?? ($beforeId ?: PHP_INT_MAX))
                ->exists(),
        ]);
    }
}
