<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Services\DashboardStatsService;
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

        if ($request->hasFile('image')) {
            // Issue #55: store() through the public disk instead of a raw
            // move() into storage_path() — same hashName() filename and the
            // same alerts/ layout, but it honors the disk configuration and
            // is visible to Storage::fake() in tests.
            $data['image'] = $request->file('image')->store('alerts', 'public');
        }

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
        $alerts = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('alerts.index', compact('alerts'));
    }

    public function adminIndex()
    {
        $alerts = Alert::with('user')->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

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
        $demotesFromApproved = ! auth()->user()->isAdmin && $alert->status === 'approved';
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
        if ($request->boolean('remove_image') && $alert->image) {
            \Storage::disk('public')->delete($alert->image);
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
        // is the exact path to remove before answering. The old file deleted
        // here needs no tracking: it is being replaced or nulled either way,
        // and on the vanished-row path the deleting() hook's own sweep (which
        // reads the row's still-current OLD path) covers it when the DELETE
        // itself lands — the asymmetry is that no row ever points at the NEW
        // file, so nothing but this variable can free it.
        $storedImage = null;
        if ($request->hasFile('image')) {
            // Nếu upload ảnh mới, xóa ảnh cũ trước (nếu có)
            if ($alert->image) {
                \Storage::disk('public')->delete($alert->image);
            }
            // Issue #55: see store() above.
            $storedImage = $data['image'] = $request->file('image')->store('alerts', 'public');
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
        // removed the row between route binding and here, Eloquent's
        // $alert->update() matched zero rows without erroring, the
        // transaction committed, and the user got a success redirect for
        // an edit that persisted nothing — while the freshly stored file was
        // referenced by no row: Alert's deleting() hook only unlinks the OLD
        // path read from the row, which this method already deleted, so the
        // new file was orphaned on disk permanently. The transaction now
        // re-reads the row after the write: a missing row answers null, the
        // caller frees the file this request stored, and the response is an
        // honest 404 — success would flash over an edit that persisted
        // nothing onto a row that no longer exists. Liveness is that re-read,
        // deliberately not an affected-rows count: MySQL reports CHANGED rows
        // (Laravel sets no MYSQLI_CLIENT_FOUND_ROWS), so a legitimate
        // double-submit of byte-identical content UPDATEs 0 rows, while
        // SQLite's driver counts MATCHED rows — the dialects disagree on the
        // number, never on the re-read. $alert->update() stays the write
        // itself (#225's semantics, model sync included): on a vanished row
        // Eloquent documents it as a silent no-op, and the exists() right
        // after is what catches exactly that case, inside the transaction so
        // the re-read sees this connection's own write.
        $outcome = DB::transaction(function () use ($alert, $data, $demotesFromApproved) {
            $alert->update($data);
            if (! Alert::whereKey($alert->id)->exists()) {
                return null;
            }
            if (! $demotesFromApproved) {
                return false;
            }

            return Alert::whereKey($alert->id)->where('status', 'pending')->exists();
        });

        if ($outcome === null) {
            if ($storedImage !== null) {
                \Storage::disk('public')->delete($storedImage);
            }
            abort(404);
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

    public function destroy(Alert $alert)
    {
        if (! auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }
        $alert->delete();

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
