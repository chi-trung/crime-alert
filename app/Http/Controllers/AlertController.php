<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Services\DashboardStatsService;
use App\Support\BellSweeps;
use App\Support\BoundedPaginator;
use App\Support\DeferredFileUnlinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    /**
     * Only administrators may run the moderation transitions below. The
     * routes already sit behind the 'admin' group (AdminMiddleware 403s
     * non-admins end-to-end), but approve()/reject() mutate state and must
     * not depend on route wiring alone — the same route-guard-only shape as
     * #97, fixed for support close/destroy in #112 with an identical helper.
     * abort_unless keeps the no-session CLI shape out: an unauthenticated
     * direct call 403s the same as a signed-in non-admin.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin, 403);
    }

    public function create()
    {
        return view('alerts.create');
    }

    public function store(Request $request)
    {
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để đăng cảnh báo.');
        }
        $request->validate([
            'title' => 'required|string|max:255',
            // Issue #37: description is a TEXT column — without a bound a
            // huge payload either 500s on MySQL's byte limit or bloats the DB.
            'description' => 'required|string|max:10000',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'confirmCheckbox' => 'accepted',
            // Issue #31: these three used to be persisted unvalidated.
            'type' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $data = $request->only(['title', 'description', 'location', 'type']);
        $data['user_id'] = Auth::id();
        $data['status'] = Auth::user()->isAdmin ? 'approved' : 'pending';
        $data['latitude'] = $request->input('latitude');
        $data['longitude'] = $request->input('longitude');

        $storedImage = null;
        if ($request->hasFile('image')) {
            // Issue #55: store() through the public disk instead of a raw
            // move() into storage_path() — same hashName() filename and the
            // same alerts/ layout, but it honors the disk configuration and
            // is visible to Storage::fake() in tests.
            //
            // Issue #281: store()'s documented contract is 'string|false',
            // and with these disks' 'throw' => false (config/filesystems.php
            // :37,46) a failed write takes the false branch — FilesystemAdapter
            // ::put() catches UnableToWriteFile and putFileAs() returns false.
            // A full or read-only production disk hits this on EVERY upload.
            // Chained straight into $data['image'], PHP casts the false to the
            // string '0': the row persists image='0', the blades build a
            // /storage/0 src that 404s, and the success flash below fires — a
            // silent broken image the user never learns about. Return a form
            // error here, before $data is touched: nothing was written to the
            // disk (that is the whole premise), so there is no file to sweep.
            $stored = $request->file('image')->store('alerts', 'public');
            if ($stored === false) {
                return back()->withInput()->withErrors(['image' => 'Không thể lưu ảnh lên server. Vui lòng thử lại.']);
            }
            $storedImage = $data['image'] = $stored;
        }

        // Issue #267: the disk write above cannot roll back, so until the
        // INSERT lands the stored file is THIS request's burden alone — no
        // row references it yet, so Alert::deleting can never free it and
        // no prune command covers storage/. Pre-fix, a create() that threw
        // (InnoDB deadlock against a concurrent moderation sweep, a
        // connection drop, or the FK violation when #266's transactional
        // destroy() commits the user delete under this still-live session)
        // 500ed and orphaned the upload permanently; a flapping user
        // retrying the form multiplies orphans. The insert and its fan-out
        // also become one transaction — update() learned this in #225 (a
        // crash between row-write and bells publishes a pending post
        // nobody will ever look at); store() had the same two-statement
        // shape and the same fix applies. On any throw: free exactly the
        // path this request stored, then rethrow the honest error.
        try {
            $alert = DB::transaction(function () use ($data) {
                $alert = Alert::create($data);
                // Gửi notification
                if (Auth::user()->isAdmin) {
                    // Admin đăng bài: gửi cho tất cả user thường
                    $users = User::where('isAdmin', false)->get();
                    foreach ($users as $user) {
                        $user->notify(new NewPostNotification($alert, Auth::user(), 'alert'));
                    }
                } else {
                    // User thường đăng bài: gửi cho tất cả admin
                    $admins = User::where('isAdmin', true)->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new NewPostPendingApprovalNotification($alert, Auth::user(), 'alert'));
                    }
                }

                return $alert;
            });
        } catch (\Throwable $e) {
            if ($storedImage !== null) {
                \Storage::disk('public')->delete($storedImage);
            }
            throw $e;
        }

        return redirect()->route('alerts.create')->with('success', 'Đăng cảnh báo thành công!');
    }

    public function index(Request $request)
    {
        $query = Alert::query();
        $query->with('user')->withCount('comments');

        // Issue #145: q/location reach the #51 escaping closure's '%…%'
        // concat and type reaches where() binding; an array (?q[]=a) passes
        // filled() and 500s both filter paths with 'Array to string
        // conversion'. The index has no other validation — these three are
        // free-form client strings, so declare their type.
        $request->validate([
            'type' => 'nullable|string',
            'location' => 'nullable|string',
            'q' => 'nullable|string',
        ]);

        // Issue #51: '%' and '_' typed into a search box are LIKE wildcards.
        // Escape them (and the escape char itself) and declare ESCAPE '!' so
        // every character matches literally on both CI databases — '!' is
        // used instead of the usual backslash because MySQL additionally
        // eats backslashes in string literals while SQLite does not.
        // str_replace with paired arrays is the correct idiom here;
        // addcslashes would prefix with backslash and break the match.
        $likeWhere = function ($column, $value) use ($query) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
            $query->whereRaw("{$column} LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
        };

        // Only approved alerts are ever listed here. The client-supplied
        // `status` filter (issue #41) AND-ed against this constant, so any
        // value other than 'approved' returned an empty page; admins have
        // their own unfiltered route (adminIndex), so nothing was lost.
        $query->where('status', 'approved');
        // Lọc theo loại tội phạm
        if ($request->filled('type')) {
            // Issue #195: the dashboard's 'Khác' tile counts every approved
            // alert whose trimmed type falls outside ALERT_TYPES (NULL,
            // '', whitespace, arbitrary free text — typeBreakdown:166-167),
            // because store/update validate type as nullable|string, never
            // an enum (#31 bounded length, not membership). The list filter
            // used equality on that same label, so /alerts?type=Khác showed
            // only rows literally typed 'Khác': on the shipped database
            // (6/6 approved alerts NULL-typed) the dashboard said Khác: 100%
            // while the filter returned zero rows — and those rows matched
            // none of the other four options either, hiding them under every
            // choice. The read path now mirrors the bucket exactly; equality
            // still serves the canonical types, and no data migration is
            // implied (free-text types stay what they are).
            if ($request->type === 'Khác') {
                $query->where(function ($q) {
                    $q->whereNull('type')
                        ->orWhere('type', '')
                        ->orWhereNotIn('type', DashboardStatsService::ALERT_TYPES);
                });
            } else {
                $query->where('type', $request->type);
            }
        }
        // Lọc theo vị trí
        if ($request->filled('location')) {
            $likeWhere('location', $request->location);
        }
        // Tìm kiếm theo tiêu đề
        if ($request->filled('q')) {
            $likeWhere('title', $request->q);
        }
        // Lọc theo bán kính (radius)
        if ($request->filled('radius') && $request->filled('lat') && $request->filled('lng')) {
            // Issue #127: all three params reach the same sprintf -> SQL-
            // literal path below; a bare (float) lets 1e999 parse to INF and
            // cos(deg2rad(INF)) = NAN interpolates the bare word NaN into the
            // raw SQL (MySQL 1054 / SQLite "no such column" — a 500 on both
            // engines). #59 clamped radius' value but not its finiteness and
            // never touched lat/lng; is_finite() catches INF, -INF and NAN
            // regardless of whether the C library parses "nan" to NAN or 0.0.
            // Outside the geographic domain there is no sensible circle to
            // answer with, so fall back to 0 like the old clamp did.
            $rawLat = (float) $request->input('lat');
            $rawLng = (float) $request->input('lng');
            $rawRadius = (float) $request->input('radius');
            $lat = is_finite($rawLat) ? min(max($rawLat, -90.0), 90.0) : 0.0;
            $lng = is_finite($rawLng) ? min(max($rawLng, -180.0), 180.0) : 0.0;
            // Clamped before it becomes a SQL literal below: radius=1e400
            // parses to INF and sprintf would interpolate the bare word.
            // 20015 km is half the earth's circumference — nothing further
            // can sit inside the circle anyway.
            $radius = is_finite($rawRadius) ? min(max($rawRadius, 0.0), 20015.0) : 0.0;
            $query->whereNotNull('latitude')->whereNotNull('longitude');
            // Issue #59: the old query used acos/cos/sin — PHP's SQLite build
            // has no math functions, so this hard-500'd on SQLite, and it
            // re-SELECTed `alerts.*` on top of the base query. Distances are
            // never rendered, so filter and order by an approximation that
            // uses only + - * and parentheses: squared equirectangular
            // distance. Within ~0.5% of the haversine at these radii — same
            // order as the original's spherical-earth error — so `dist <= r`
            // and `ORDER BY dist` hold for any point not sitting exactly on
            // the circle. cos, the km/degree scale and the squared radius are
            // folded into PHP-side float literals (sprintf %.6F, so always an
            // ASCII dot). The squared radius must NOT bind as a parameter:
            // Laravel sends non-int values to PDO as PARAM_STR, and in SQLite
            // any number sorts below any text, so `dist2 <= ?` with a
            // float-bound radius is always true and the filter silently keeps
            // every row. The coordinates do bind: subtraction coerces a text
            // binding back to a number on both databases.
            $kmPerDeg = sprintf('%.6F', 6371.0 * M_PI / 180.0);
            $kmPerLng = sprintf('%.6F', 6371.0 * M_PI / 180.0 * cos(deg2rad($lat)));
            $radius2 = sprintf('%.10F', $radius * $radius);
            $dLat = "(latitude - ?) * {$kmPerDeg}";
            $dLng = "(longitude - ?) * {$kmPerLng}";
            $dist2 = "({$dLat}) * ({$dLat}) + ({$dLng}) * ({$dLng})";
            $query->whereRaw("{$dist2} <= {$radius2}", [$lat, $lat, $lng, $lng])
                ->orderByRaw($dist2, [$lat, $lat, $lng, $lng]);
        }

        // Issue #81: created_at is second-resolution, so same-second rows had
        // no deterministic order and page boundaries shuffled on refresh —
        // the id tiebreak #75 established for the support lists.
        // Issue #317: bounded page — see WantedListController for the probe.
        $alerts = BoundedPaginator::paginate($query->orderByDesc('created_at')->orderByDesc('id'), 10)->withQueryString();

        return view('alerts.index', compact('alerts'));
    }

    public function adminIndex()
    {
        // Issue #317: ?page=9223372036854775800 made this footer read
        // "Hiển thị 1.3835058055282E+20 đến ... trong 3 kết quả" (firstItem()
        // overflowed to float), and an int page past the end (20 of 3) kept
        // total()>0 while the page itself was empty — blank spans.
        $alerts = BoundedPaginator::paginate(Alert::with('user')->orderByDesc('created_at')->orderByDesc('id'), 15);

        return view('alerts.admin_index', compact('alerts'));
    }

    public function approve(Alert $alert)
    {
        // Issue #117: in-method admin assertion (see authorizeAdmin()).
        $this->authorizeAdmin();
        // Issue #189: the old blind write let a stale moderation page (two
        // admins, or one admin in two tabs — the UI renders the buttons only
        // while the row is pending and never auto-refreshes) flip an
        // already-approved alert back to rejected or resurrect a rejected
        // one, both answered with the normal success flash: silent
        // un-moderation of public scam warnings, the exact misleading
        // repeat-click class #98 fixed for support close(). The write is now
        // one conditional UPDATE (same check-then-act hardening idiom as
        // #139/#153): it lands only while status is still 'pending', so the
        // guard cannot race a concurrent decide, and 0 affected rows tells
        // the acting admin the item was already handled.
        $decided = Alert::whereKey($alert->id)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'updated_at' => now()]);
        if (! $decided) {
            return back()->with('info', 'Cảnh báo này đã được xử lý trước đó.');
        }

        return back()->with('success', 'Đã duyệt cảnh báo thành công!');
    }

    public function reject(Alert $alert)
    {
        // Issue #117: same in-method assertion as approve() above.
        $this->authorizeAdmin();
        // Issue #189: conditional pending-only write — see approve().
        $decided = Alert::whereKey($alert->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'updated_at' => now()]);
        if (! $decided) {
            return back()->with('info', 'Cảnh báo này đã được xử lý trước đó.');
        }

        return back()->with('success', 'Đã từ chối cảnh báo!');
    }

    public function edit(Alert $alert)
    {
        // Cho phép admin hoặc chủ bài được sửa
        if (! auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }

        return view('alerts.edit', compact('alert'));
    }

    public function update(Request $request, Alert $alert)
    {
        // Issue #237: #129's verified-email invariant was enforced only in
        // store(), so an owner could PATCH /profile (which nulls
        // email_verified_at while the session stays authenticated) and keep
        // full write rights over published content — including this method's
        // #225 demote-and-re-bell fan-out, the very notification store()
        // gates. The same first-statement idiom as store() above.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để chỉnh sửa cảnh báo.');
        }
        if (! auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            // Issue #37: description is a TEXT column — without a bound a
            // huge payload either 500s on MySQL's byte limit or bloats the DB.
            'description' => 'required|string|max:10000',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // Issue #31: mirrors store() — junk coords and oversized type
            // used to be written straight to the database.
            'type' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $data = $request->only(['title', 'description', 'location', 'type']);
        // Mirrors ExperienceController::update (issue #23): content that passes
        // moderation must not be silently rewritable by its owner afterwards.
        // Any edit by a non-admin demotes the alert to pending so a reviewer
        // sees the new text. Admin edits keep whatever status they had.
        //
        // Issue #225: capture the transition before mutating $data — store()
        // guarantees every new 'pending' alert rings NewPostPendingApproval-
        // Notification for every admin, but this second entry into the queue
        // was silent: a re-queued rewrite appeared on no bell, so the reviewer
        // #23 promises never learns WHICH post just came back. Gating on
        // approved->pending keeps already-pending edits from re-belling.
        //
        // Issue #257: 'approved' was only HALF the queue's front door. The
        // force below moves EVERY non-admin edit to 'pending', including a
        // REJECTED post — so an owner's rewrite silently undid the reject
        // decision with zero bells, violating the invariant #225 states in
        // so many words (every arrival in the queue must name itself). The
        // gate now covers both out-of-review transitions, while a still
        // 'pending' edit stays silent: it was already belling.
        // Issue #287: the bell gate itself moved INTO the transaction below,
        // as a locking current read of status. Computing it here from the
        // route binding's pre-transaction read let a mid-flight approve()/
        // reject() (a status-only write the #265 guard deliberately does not
        // exclude it from contending on) flip the row to 'approved' behind a
        // gate that still said 'pending' -> false: this request then
        // resurrected the just-decided row into the queue with zero bells —
        // exactly the silent arrival #225/#257 forbid. Only the forced
        // demote stays here; the decision of whether it DEMOTES INTO the
        // queue (vs re-queues a stale 'pending') is re-derived live.
        if (! auth()->user()->isAdmin) {
            $data['status'] = 'pending';
        }
        $data['latitude'] = $request->input('latitude');
        $data['longitude'] = $request->input('longitude');
        // Xử lý xóa ảnh nếu có chọn
        // Issue #125: the edit form renders a hidden remove_image=0 whenever an
        // image exists (edit.blade.php:46); the page JS flips it to '1' only on
        // a click. The old has('remove_image') presence check matched the
        // always-present '0', so every ordinary edit took this branch and
        // destroyed the stored image. boolean() maps '1' -> true, '0'/absent ->
        // false, so only an explicit removal reaches the delete. This restores
        // the #29 keep-branch below for normal submissions.
        //
        // Issue #255: $oldImage is the value this request READ from the row,
        // captured here once because three things below need the same value:
        // the write guard, the post-commit unlink, and (before the fix,
        // fatally) it was deleted from the stale in-memory model before the
        // write even happened. The unlink is now DEFERRED to after a durable
        // write — see the transaction comment — so a losing racer never
        // destroys a file the surviving row still references.
        $oldImage = $alert->image;
        // Issue #265: the same "exact value this request read" discipline for
        // every remaining column update() contends on. Captured HERE, once,
        // before anything below can mutate the in-memory model; the guarded
        // UPDATE keys on it and the staleness re-read compares against it.
        $contentSnapshot = [
            'title' => $alert->title,
            'description' => $alert->description,
            'location' => $alert->location,
            'type' => $alert->type,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
        ];
        if ($request->boolean('remove_image') && $alert->image) {
            $data['image'] = null;
        } elseif (! $request->hasFile('image')) {
            // No upload and no removal: keep the stored image. The old code
            // trusted a client-supplied `old_image` hidden input here, which
            // let any caller write an arbitrary string into the column
            // (issue #29). The server already knows the truth.
            $data['image'] = $alert->image;
        }
        // Issue #233: remember what THIS request wrote to the public disk.
        // The store() below is not transactional and cannot roll back, so if
        // the conditional persistence at the bottom finds the row gone, this
        // is the exact path to remove before answering. The asymmetry is that
        // no row ever points at the NEW file, so nothing but this variable
        // can free it.
        $storedImage = null;
        if ($request->hasFile('image')) {
            // Issue #55: see store() above.
            // Issue #255: the old image is NOT deleted here anymore. A disk
            // unlink cannot roll back, and until the guarded write below
            // lands, this request does not yet know whether the row still
            // sits on $oldImage — the early delete destroyed the live file
            // underneath a concurrent winner whose path it could not see.
            //
            // Issue #281: same false contract as store() above, and worse
            // here — the write lands via the #265 guard, which cannot tell
            // '0' from the false it expects to read back, so the guarded
            // UPDATE commits title + image='0' over a perfectly good
            // $oldImage (orphaning that file: the column no longer points at
            // it, and destroy() later unlinks '0', which matches nothing),
            // and the staleness re-read's '0'-vs-(string)false mismatch then
            // answers "your changes were not saved" for an edit that DID
            // save. Bailing out before $data['image'] is assigned keeps the
            // old path in the guard's expected value — the row, the file and
            // the next request's $oldImage all survive intact.
            $stored = $request->file('image')->store('alerts', 'public');
            if ($stored === false) {
                return back()->withInput()->withErrors(['image' => 'Không thể lưu ảnh lên server. Vui lòng thử lại.']);
            }
            $storedImage = $data['image'] = $stored;
        }
        // Issue #225: the demote-write and its admin fan-out used to be two
        // autocommitted statements, so a crash between them left a pending
        // rewrite with no bell at all. Same transaction-plus-re-read shape
        // #139 uses for like notifications: the re-read confirms the row is
        // actually sitting in the queue (a concurrent reject from a stale
        // moderation page can land the row on 'rejected' instead, and
        // #189's approve guard then clears it) before we ring. Documented
        // residual, identical to #139's: two same-content submits can each
        // see 'pending' and bell twice — never zero, never orphaned.
        //
        // Issue #233: the file store() above already landed on the public
        // disk BEFORE and OUTSIDE this transaction — a disk write cannot
        // roll back. If a concurrent DELETE (admin moderation, the owner's
        // own destroy, or ProfileController::destroy's account sweep)
        // removed the row between route binding and here, the transaction
        // re-read finds it: the caller frees the file this request stored
        // and the response is an honest 404.
        //
        // Issue #255: the sibling race — two replacement uploads landing
        // around each other (double-click, two tabs; throttle 5/min lets
        // two through easily). Old shape: both requests read image=A, R2
        // commits image=F2, then R1's blind write commits image=F1 — F2
        // referenced by no row, and #233's sweep only ran for a VANISHED
        // row, so neither request freed it: a permanent orphan, because
        // deleting() later unlinks only the current path. The write is now
        // an image-guarded UPDATE keyed on the exact value this request
        // read: a stale writer matches zero rows, and the in-transaction
        // re-read of the image column decides the outcome instead of any
        // affected-rows count — MySQL reports CHANGED (a keep-branch or
        // byte-identical write legitimately changes nothing) while SQLite
        // counts MATCHED; #233's doctrine holds, the re-read is the only
        // signal both dialects agree on. Zero-matched-but-alive answers
        // 'stale': this request frees its own stored file, rings no bell,
        // and the user is told to reload rather than being flashed a
        // success that persisted nothing. A true winner unlinks $oldImage
        // only after the durable write.
        //
        // Issue #265: image alone was only half the predicate. An ordinary
        // content edit never touches the column — the keep-branch copies the
        // UNCHANGED snapshot value into $data['image'] — so two content
        // edits racing each other both satisfied where('image', $oldImage),
        // both survived the image re-read, and the later committer
        // destroyed the earlier payload under two success flashes and two
        // admin fan-outs (#255's stale path can never fire for
        // content-vs-content because those writers don't contend on image
        // at all). The guard now keys on EVERY column this request
        // contends on — #255's "guard on the exact value this request read"
        // doctrine applied to its full scope. Deliberately NOT updated_at:
        // second-resolution timestamps make an updated_at predicate blind
        // inside the one-second window where two-tab races actually live
        // (#89's same-second family), while a rival's content write always
        // changes a content value. The staleness re-read compares the same
        // columns against what this request wrote, so a zero-matched guard
        // is still caught without trusting any affected-rows count
        // (#233's CHANGED-vs-MATCHED dialect split doctrine holds).
        // Residuals, each out of scope here as before: a rival that changed
        // only coordinates on an otherwise byte-identical edit is covered
        // too (coords are guarded). A mid-flight moderation transition
        // (status-only write by approve()/reject()) used to be listed here
        // as "#189's shape answered by its own guards" — but those guards
        // only stop double-clicked moderation; the BELL gate on the binding's
        // stale status let this request silently re-queue a decided row
        // (issue #287). Fixed now: the transaction's first statement is a
        // lockForUpdate status read, so the gate sees the live status AND
        // the row lock keeps approve()/reject() out of the rest of this
        // transaction.
        // Issue #289: store() learned in #267 that a disk write cannot roll
        // back with the transaction that persists its path — update() never
        // mirrored the lesson. The replacement file above is THIS request's
        // burden until the guarded write lands, so every throw inside the
        // transaction (the 1205 the #287 row lock invites from a concurrent
        // moderation transaction, a deadlock, a connection drop) must free
        // exactly the path this request stored before rethrowing the honest
        // error. The null/'stale' arms below already sweep $storedImage; the
        // throw arm was simply missing.
        try {
            $outcome = DB::transaction(function () use ($alert, $data, $oldImage, $contentSnapshot) {
                // Issue #287: the FIRST statement of this transaction takes the
                // status as a locking current read (#163's doctrine), which also
                // holds the row lock across the rest of the transaction — an
                // approve()/reject() can no longer slip a status-only write
                // between the gate and the guarded UPDATE below. The gate then
                // reads the LIVE status, not the binding's pre-transaction one:
                // a pending->approved commit that landed before this lock read
                // counts as an arrival (bell), and a plain pending->pending edit
                // still stays silent per #225.
                $liveStatus = Alert::whereKey($alert->id)->lockForUpdate()->value('status');
                Alert::whereKey($alert->id)
                    ->where('image', $oldImage)
                    ->where($contentSnapshot)
                    ->update($data + ['updated_at' => now()]);
                if (! Alert::whereKey($alert->id)->exists()) {
                    return null;
                }
                // Raw fetch, not ->first(): hydrating an Alert would fire the
                // retrieved event the race probes arm on (#163 idiom) a second
                // time; the builder reads used above already sidestep it.
                $live = DB::table('alerts')->where('id', $alert->id)->first();
                // ?? $oldImage: an edit that touches no image leaves the column
                // out of $data entirely, so the value we expect to read back is
                // whatever the guarded write started from.
                if (! $this->sameColumnValue($live->image, array_key_exists('image', $data) ? $data['image'] : $oldImage)) {
                    return 'stale';
                }
                foreach ($contentSnapshot as $col => $startValue) {
                    $expected = array_key_exists($col, $data) ? $data[$col] : $startValue;
                    if (! $this->sameColumnValue($live->$col, $expected)) {
                        return 'stale';
                    }
                }
                // The guarded builder update skips model events and the
                // in-memory sync $alert->update() used to give; the demote re-read
                // below and the notification payload both still expect the model
                // to carry the just-written row.
                $alert->refresh();
                // Issue #287: gate on the locking current read taken at the top
                // of this transaction (the status BEFORE our own write forced
                // 'pending'), not on a pre-transaction binding read.
                if (auth()->user()->isAdmin || ! in_array($liveStatus, ['approved', 'rejected'], true)) {
                    return false;
                }

                return Alert::whereKey($alert->id)->where('status', 'pending')->exists();
            });
        } catch (\Throwable $e) {
            if ($storedImage !== null) {
                \Storage::disk('public')->delete($storedImage);
            }

            throw $e;
        }

        if ($outcome === null) {
            if ($storedImage !== null) {
                \Storage::disk('public')->delete($storedImage);
            }
            abort(404);
        }

        if ($outcome === 'stale') {
            if ($storedImage !== null) {
                // Issue #255: the mirror of #233's vanish sweep — the row is
                // alive, it just belongs to a newer write now, and no row
                // will ever point at this request's file.
                \Storage::disk('public')->delete($storedImage);
            }

            return redirect()->back()->with('info', 'Cảnh báo vừa được cập nhật ở nơi khác; thay đổi của bạn chưa được lưu.');
        }

        // Issue #255: the old file had no row pointing at it the MOMENT this
        // request's guarded write landed — it is only safe to free now, and
        // only now that it is proven safe. remove_image reaches here with
        // $data['image'] null, replacement with the new path; a keep-branch
        // edit sees its own value back and deletes nothing. The winner's
        // unlink and a rival's (identical) unlink are both no-ops on the
        // second arrival, so the surviving request always frees the file.
        //
        // Issue #289: this unlink must stay BEFORE the fan-out below. The
        // bells are synchronous writes after an already-durable swap, so a
        // throw from the notification path 500s WITHOUT rolling the row back
        // — and the row now points at the new file, so deleting() will never
        // free the displaced one either. Unlink first and the worst a broken
        // fan-out costs is missing bells (#225's documented trade), never a
        // permanent orphan.
        if ($oldImage !== null && (array_key_exists('image', $data) ? $data['image'] : $oldImage) !== $oldImage) {
            \Storage::disk('public')->delete($oldImage);
        }

        if ($outcome) {
            $admins = User::where('isAdmin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewPostPendingApprovalNotification($alert, auth()->user(), 'alert'));
            }
        }

        // Sau khi cập nhật, redirect về dashboard
        return redirect()->route('dashboard')->with('success', 'Cập nhật cảnh báo thành công!');
    }

    /**
     * Issue #265: dialect-tolerant column equality for the staleness
     * re-read. A DECIMAL coordinate comes back as '10.5000000' on MySQL
     * where the form sent '10.5' (and sqlite may hand back a float where
     * the snapshot holds a string), so a naive !== would false-'stale'
     * every honest edit that round-trips coordinates. Two numerics
     * therefore compare as floats; everything else settles null-vs-non-null
     * first, then compares as strings.
     */
    private function sameColumnValue(mixed $live, mixed $expected): bool
    {
        if ($live === null || $expected === null) {
            return $live === null && $expected === null;
        }
        if (is_numeric($live) && is_numeric($expected)) {
            return (float) $live === (float) $expected;
        }

        return (string) $live === (string) $expected;
    }

    public function destroy(Alert $alert)
    {
        // Issue #237: same gate as update() — lower impact (self-deletion)
        // but the invariant is per-endpoint-class, and store() already
        // refuses writes from unverified accounts.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để xóa cảnh báo.');
        }
        if (! auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }

        // Issue #311: was a bare $alert->delete() — the #271 window, unfixed
        // on this route. The #121 notification sweep fires on Alert::deleting
        // BEFORE the DELETE acquires the alerts row's X lock, so a comment
        // fan-out holding that lock (#153's lockForUpdate target read,
        // INSIDE whose transaction NewCommentOnPost is written through the
        // synchronous database channel) commits its bell after the sweep
        // already answered. notifications has no FK to alerts (the morph
        // keys the recipient — #102's whole premise), the alert row is
        // gone so the hook can never fire for it again: a permanent orphan
        // whose /alerts/N url 404s and which inflates the unread badge
        // forever. Same closing as #271 for support destroy: lock the row
        // FIRST (a rival still inside its fan-out transaction then sees the
        // wait and its own re-reads resolve against the dead row), delete,
        // and sweep the notifications table to a FIXED POINT after the
        // delete — on sqlite compileLock is a no-op, so the sweep alone is
        // what closes it there (same dialect honesty as #271/#266). The
        // exists() probe DECIDES (#307's consumption of #285's doctrine):
        // a rival destroy committing between this route binding's snapshot
        // hydration and the lock read 404s instead of flashing success for
        // a post this request did not delete. Raw exists()/pluck: hydrates
        // no model, fires no retrieved event (#163 probes stay armable),
        // and the file unlink keeps its #289 current read inside the hook.
        // The transaction moves the hook's unlink inside a rollback-able
        // scope for the first time on this route, so #309's ledger applies:
        // arm around the extent, drain only after a real commit, discard on
        // throw — a late rollback (e.g. the sweep deadlocking with a rival
        // transaction on MySQL) must resurrect the row WITH its file, not
        // over a deleted one. Same caller-owned scope as ProfileController.
        DeferredFileUnlinks::arm();

        try {
            $deleted = DB::transaction(function () use ($alert): bool {
                if (! Alert::whereKey($alert->id)->lockForUpdate()->exists()) {
                    return false;
                }

                $alert->delete();

                do {
                    $swept = BellSweeps::sweepPost($alert->id, 'alert');
                } while ($swept > 0);

                return true;
            });
        } catch (\Throwable $e) {
            DeferredFileUnlinks::discard();

            throw $e;
        }

        if (! $deleted) {
            // Vanished row: nothing was captured, but the gate must not stay
            // armed past this request — an abort skips the drain below, and
            // a static left armed would silently swallow every later unlink
            // the process serves (long-lived workers persist).
            DeferredFileUnlinks::discard();

            abort(404);
        }

        DeferredFileUnlinks::drain();

        // Sau khi xoá, redirect về dashboard
        return redirect()->route('dashboard')->with('success', 'Đã xoá cảnh báo!');
    }

    public function show(Alert $alert)
    {
        // Same visibility rule as experiences (issue #20): unapproved alerts
        // are readable only by their owner or an admin, not the public.
        if ($alert->status !== 'approved'
            && ! (Auth::check() && (Auth::user()->isAdmin || Auth::id() === $alert->user_id))) {
            abort(403);
        }

        return view('alerts.show', compact('alert'));
    }

    public function mapView()
    {
        // Issue #79: this selected every column of every approved, geocoded
        // alert and the blade @json'd the whole model set into
        // window.ALERTS_DATA, so description/image/user_id/status/timestamps
        // rode along to each viewer. alerts_map.js consumes exactly these six
        // fields, so these are the only six columns read. (Marker clustering
        // legitimately needs the whole set, hence no limit here.)
        $alerts = Alert::where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'title', 'type', 'location', 'latitude', 'longitude']);

        return view('alerts.map', compact('alerts'));
    }
}
