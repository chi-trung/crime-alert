<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $query->where('type', $request->type);
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
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            // Clamped before it becomes a SQL literal below: radius=1e400
            // parses to INF and sprintf would interpolate the bare word.
            // 20015 km is half the earth's circumference — nothing further
            // can sit inside the circle anyway.
            $radius = min(max((float) $request->input('radius'), 0.0), 20015.0);
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
        $alert->status = 'approved';
        $alert->save();

        return back()->with('success', 'Đã duyệt cảnh báo thành công!');
    }

    public function reject(Alert $alert)
    {
        // Issue #117: same in-method assertion as approve() above.
        $this->authorizeAdmin();
        $alert->status = 'rejected';
        $alert->save();

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
        if ($request->hasFile('image')) {
            // Nếu upload ảnh mới, xóa ảnh cũ trước (nếu có)
            if ($alert->image) {
                \Storage::disk('public')->delete($alert->image);
            }
            // Issue #55: see store() above.
            $data['image'] = $request->file('image')->store('alerts', 'public');
        }
        $alert->update($data);

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
