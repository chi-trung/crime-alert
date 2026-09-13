<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use App\Models\User;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'name' => 'required|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        $data['user_id'] = Auth::id();
        $data['status'] = Auth::user() && Auth::user()->isAdmin ? 'approved' : 'pending';
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
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
        if (Auth::id() !== $experience->user_id && ! Auth::user()->isAdmin) {
            abort(403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:10000',
            'name' => 'required|string|max:100',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        // Issue #73: this used to demote unconditionally, so an admin fixing a
        // typo on an approved post silently threw it back into the moderation
        // queue its approval had just cleared — the opposite of what
        // AlertController::update, whose comment claims to mirror this method,
        // does. The rule (issue #23) is about owners rewriting content that
        // already passed moderation; a reviewer editing is not that. Admin
        // edits now keep the post's status, mirroring alerts.
        if (! Auth::user()->isAdmin) {
            $data['status'] = 'pending';
        }
        $experience->update($data);

        return redirect()->route('experiences.show', $experience)->with('success', 'Cập nhật bài chia sẻ thành công!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Experience $experience)
    {
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
