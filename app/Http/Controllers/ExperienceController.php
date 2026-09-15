<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExperienceController extends Controller
{
    /**
     * Only administrators may run the moderation transitions below. The
     * routes already sit behind the 'can:admin' group, but approve()/reject()
     * mutate state and must not depend on route wiring alone — same
     * route-guard-only shape as #97 (support, fixed in #112) and #117's
     * sibling AlertController::approve()/reject().
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin, 403);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Issue #81: id tiebreak for stable page boundaries (see #75).
        $experiences = Experience::with('user')->where('status', 'approved')->orderByDesc('created_at')->orderByDesc('id')->paginate(9);

        return view('experiences.index', compact('experiences'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('experiences.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để đăng bài chia sẻ.');
        }
        $request->validate([
            'title' => 'required|string|max:255',
            // Issue #37: TEXT column needs a bound (see AlertController).
            'content' => 'required|string|max:10000',
            // Issue #224: 'name' is supplied by the form itself (the auth
            // branch posts a hidden input with Auth::user()->name) and both
            // registration and PATCH /profile accept up to max:255 — the
            // varchar(255) column's own width — so the legacy 100 cap made
            // a legal 101-255-char name unpublishable, invisibly (the
            // rejection error rendered nowhere for authed users). Aligned
            // to 255; the display branches in create/edit now surface
            // @error('name') so a future rejection can never be silent.
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        $data['user_id'] = Auth::id();
        $data['status'] = Auth::user() && Auth::user()->isAdmin ? 'approved' : 'pending';
        // Issue #267: mirror of the AlertController::store() sweep — the
        // avatar write cannot roll back, so a create() that throws
        // (deadlock, connection drop, #266's FK window) orphaned the file
        // in avatars/ permanently; no row, no hook, no prune command.
        // Insert and fan-out now commit together, and any throw frees
        // exactly the path this request stored before rethrowing.
        $storedAvatar = null;
        if ($request->hasFile('avatar')) {
            // Issue #281: mirror of the AlertController fix — store()'s
            // documented 'string|false' contract with these disks'
            // 'throw' => false, chained straight into $data, would persist
            // avatar='0' under a success flash (broken image for every
            // reader) whenever the public disk fails a write. The experiences
            // form currently posts no avatar input, so this leg is reached by
            // a crafted multipart request — but the route is auth'd and the
            // field validated, so it must fail honestly. Error before $data
            // is touched; a false store wrote nothing, so nothing to sweep.
            $stored = $request->file('avatar')->store('avatars', 'public');
            if ($stored === false) {
                return back()->withInput()->withErrors(['avatar' => 'Không thể lưu ảnh đại diện lên server. Vui lòng thử lại.']);
            }
            $storedAvatar = $data['avatar'] = $stored;
        }
        try {
            DB::transaction(function () use ($data) {
                $exp = Experience::create($data);
                // Gửi notification
                if (Auth::user() && Auth::user()->isAdmin) {
                    // Admin đăng bài: gửi cho tất cả user thường
                    $users = User::where('isAdmin', false)->get();
                    foreach ($users as $user) {
                        $user->notify(new NewPostNotification($exp, Auth::user(), 'experience'));
                    }
                } else {
                    // User thường đăng bài: gửi cho tất cả admin
                    $admins = User::where('isAdmin', true)->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new NewPostPendingApprovalNotification($exp, Auth::user(), 'experience'));
                    }
                }
            });
        } catch (\Throwable $e) {
            if ($storedAvatar !== null) {
                \Storage::disk('public')->delete($storedAvatar);
            }
            throw $e;
        }
        $msg = Auth::user() && Auth::user()->isAdmin ? 'Bài chia sẻ của bạn đã được duyệt!' : 'Bài chia sẻ của bạn đã gửi và chờ duyệt!';

        return redirect()->route('experiences.index')->with('success', $msg);
    }

    /**
     * Display the specified resource.
     */
    public function show(Experience $experience)
    {
        if ($experience->status !== 'approved' && ! (Auth::check() && (Auth::user()->isAdmin || Auth::id() === $experience->user_id))) {
            abort(403);
        }

        return view('experiences.show', compact('experience'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Experience $experience)
    {
        if (Auth::id() !== $experience->user_id && ! Auth::user()->isAdmin) {
            abort(403);
        }

        return view('experiences.edit', compact('experience'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Experience $experience)
    {
        // Issue #237: #129's gate lived only in store(); an unverified
        // account (e.g. after a PATCH /profile email change) could still
        // rewrite its published post and fire this method's #225 admin
        // re-bell fan-out. Same first-statement idiom as store() above.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để chỉnh sửa bài chia sẻ.');
        }
        if (Auth::id() !== $experience->user_id && ! Auth::user()->isAdmin) {
            abort(403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:10000',
            // Issue #224: same cap alignment as store() above — the edit
            // form re-posts the stored name, which may legally be up to 255
            // chars, so the 100 bound silently blocked every edit by
            // long-named owners.
            'name' => 'required|string|max:255',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        // Issue #73: this used to demote unconditionally, so an admin fixing a
        // typo on an approved post silently threw it back into the moderation
        // queue its approval had just cleared — the opposite of what
        // AlertController::update, whose comment claims to mirror this method,
        // does. The rule (issue #23) is about owners rewriting content that
        // already passed moderation; a reviewer editing is not that. Admin
        // edits now keep the post's status, mirroring alerts.
        //
        // Issue #225: capture the transition before mutating $data — store()
        // guarantees every new 'pending' experience rings NewPostPending-
        // ApprovalNotification for every admin, but this second entry into
        // the queue was silent. Gating on approved->pending keeps
        // already-pending edits from re-belling.
        //
        // Issue #257: 'approved' alone missed the queue's other front door —
        // the force below moves EVERY non-admin edit to 'pending', so a
        // rejected post rewritten by its owner silently undid the reject
        // decision with zero bells. Both out-of-review transitions now ring.
        // Issue #287: the bell gate moved INTO the transaction below as a
        // locking current read of status — see AlertController::update() for
        // the full rationale. A binding-computed gate let a mid-flight
        // approve()/reject() resurrect the just-decided row into the queue
        // with zero bells, violating #225/#257. Only the forced demote stays
        // here; whether it DEMOTES INTO the queue is re-derived live.
        if (! Auth::user()->isAdmin) {
            $data['status'] = 'pending';
        }
        // Issue #225: demote-write and admin fan-out in one transaction with
        // a re-read before ringing — see AlertController::update() for the
        // full rationale and the documented double-submit residual.
        // Issue #247: mirrors #233's vanish arm. A concurrent delete (owner
        // or admin in another tab) between authorization and this write made
        // $experience->update() a silent no-op and the request then redirected
        // to experiences.show for a row that no longer exists — a 404 page
        // claiming "Cập nhật bài chia sẻ thành công!". The exists() check
        // inside the same transaction catches the no-op; experiences carry no
        // image field, so unlike alerts there is no stored file to purge.
        //
        // Issue #275: the OTHER half of the alerts fix — #255/#265's
        // stale-writer guard — was never mirrored here, and #247's own
        // comment above shows how the port got scoped to the vanish/file
        // shape. Two content edits from the same owner (two tabs; the route's
        // throttle:5,1 budget admits both, and edit() lets admin and owner
        // co-edit) never contended on anything: both blind writes matched the
        // live row, the later committer destroyed the earlier payload, BOTH
        // flashed success, and — worse on the moderation side — because the
        // winner's write also left 'pending', the loser's demote re-read at
        // the bottom of this transaction PASSED and admins received a second
        // NewPostPendingApprovalNotification for a post whose content the
        // loser never actually wrote. The write is now an UPDATE guarded on
        // the exact title/content/name this request read (#255's discipline
        // over every column #265 declared contended), and a zero-matched-
        // but-alive row is decided by the in-transaction re-read, never by
        // affected-rows count (MySQL reports CHANGED, SQLite MATCHED — #233's
        // dialect-split doctrine holds; byte-identical double-submits
        // legitimately change nothing and must not false-'stale'). Residual
        // updated_at stays excluded for #89's second-resolution blindness
        // inside the one-second race window. The mid-flight moderation
        // transition that used to be listed here as "answered by #189's own
        // guards" is a BELL problem those guards never addressed: the gate
        // read the binding's stale status, so approve()/reject() committing
        // behind it let this request re-queue a decided row with zero bells
        // (issue #287). Fixed now — this transaction OPENS with a
        // lockForUpdate status read and the gate uses that live value.
        $contentSnapshot = [
            'title' => $experience->title,
            'content' => $experience->content,
            'name' => $experience->name,
        ];
        $outcome = DB::transaction(function () use ($experience, $data, $contentSnapshot) {
            // Issue #287: first statement = locking current read of status
            // (#163 doctrine), which also holds the row lock across this
            // transaction so no approve()/reject() status-only write can slip
            // between the gate and the guarded UPDATE. The gate reads the
            // LIVE status; a plain pending->pending edit still stays silent
            // per #225. Builder ->value(): no model hydration, no retrieved
            // event — the race probes below stay armable exactly as before.
            $liveStatus = Experience::whereKey($experience->id)->lockForUpdate()->value('status');
            Experience::whereKey($experience->id)
                ->where($contentSnapshot)
                ->update($data + ['updated_at' => now()]);
            if (! Experience::whereKey($experience->id)->exists()) {
                return null;
            }
            // Raw fetch, not ->first(): hydrating an Experience would fire
            // the retrieved event the race probes arm on (#163 idiom) a
            // second time; the builder reads above already sidestep it.
            $live = DB::table('experiences')->where('id', $experience->id)->first();
            foreach ($contentSnapshot as $col => $startValue) {
                $expected = array_key_exists($col, $data) ? $data[$col] : $startValue;
                if ((string) $live->$col !== (string) $expected) {
                    return 'stale';
                }
            }
            // The guarded builder update skips model events and the
            // in-memory sync $experience->update() used to give; the demote
            // re-read below and the notification payload both still expect
            // the model to carry the just-written row.
            $experience->refresh();
            // Issue #287: gate on the locking current read taken at the top
            // of this transaction (the status BEFORE our own forced 'pending'
            // write), not on the binding's pre-transaction read.
            if (Auth::user()->isAdmin || ! in_array($liveStatus, ['approved', 'rejected'], true)) {
                return false;
            }

            return Experience::whereKey($experience->id)->where('status', 'pending')->exists();
        });

        if ($outcome === null) {
            abort(404);
        }

        if ($outcome === 'stale') {
            // The mirror of #255's reload notice: the row lives, it just
            // belongs to a newer write now — persist nothing, ring nothing,
            // and never flash a success that saved nothing.
            return redirect()->back()->with('info', 'Bài chia sẻ vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');
        }

        if ($outcome) {
            $admins = User::where('isAdmin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewPostPendingApprovalNotification($experience, Auth::user(), 'experience'));
            }
        }

        return redirect()->route('experiences.show', $experience)->with('success', 'Cập nhật bài chia sẻ thành công!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Experience $experience)
    {
        // Issue #237: same gate as update().
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để xóa bài chia sẻ.');
        }
        if (Auth::id() !== $experience->user_id && ! Auth::user()->isAdmin) {
            abort(403);
        }
        $experience->delete();

        return redirect()->route('experiences.index')->with('success', 'Đã xóa bài chia sẻ!');
    }

    // Trang quản lý cho admin
    public function adminIndex()
    {
        $experiences = Experience::orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        return view('experiences.admin_index', compact('experiences'));
    }

    // Duyệt bài
    public function approve(Experience $experience)
    {
        // Issue #117: in-method admin assertion (see authorizeAdmin()).
        $this->authorizeAdmin();
        // Issue #189: the blind write let a stale moderation page resurrect
        // a rejected experience or silently un-approve a public one, both
        // answered with the success flash. Mirrors the fix in
        // AlertController::approve() — one conditional pending-only UPDATE
        // (check-then-act hardening idiom of #139/#153) after the #98 guard
        // pattern from support close().
        $decided = Experience::whereKey($experience->id)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'updated_at' => now()]);
        if (! $decided) {
            return back()->with('info', 'Bài chia sẻ này đã được xử lý trước đó.');
        }

        return back()->with('success', 'Đã duyệt bài chia sẻ!');
    }

    public function reject(Experience $experience)
    {
        // Issue #117: same in-method assertion as approve() above.
        $this->authorizeAdmin();
        // Issue #189: conditional pending-only write — see approve().
        $decided = Experience::whereKey($experience->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'updated_at' => now()]);
        if (! $decided) {
            return back()->with('info', 'Bài chia sẻ này đã được xử lý trước đó.');
        }

        return back()->with('success', 'Đã từ chối bài chia sẻ!');
    }
}
