<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExperienceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $experiences = Experience::where('status', 'approved')->orderByDesc('created_at')->paginate(9);
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
        if (!Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để đăng bài chia sẻ.');
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
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
            $users = \App\Models\User::where('isAdmin', false)->get();
            foreach ($users as $user) {
                $user->notify(new \App\Notifications\NewPostNotification($exp, Auth::user(), 'experience'));
            }
        } else {
            // User thường đăng bài: gửi cho tất cả admin
            $admins = \App\Models\User::where('isAdmin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\NewPostPendingApprovalNotification($exp, Auth::user(), 'experience'));
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
        if ($experience->status !== 'approved' && !(Auth::check() && (Auth::user()->isAdmin || Auth::id() === $experience->user_id))) {
            abort(403);
        }
        return view('experiences.show', compact('experience'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Experience $experience)
    {
        if (Auth::id() !== $experience->user_id && !Auth::user()->isAdmin) {
            abort(403);
        }
        return view('experiences.edit', compact('experience'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Experience $experience)
    {
        if (Auth::id() !== $experience->user_id && !Auth::user()->isAdmin) {
            abort(403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'name' => 'required|string|max:100',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        // Khi user sửa, luôn chuyển về trạng thái chờ duyệt lại
        $data['status'] = 'pending';
        $experience->update($data);
        return redirect()->route('experiences.show', $experience)->with('success', 'Cập nhật bài chia sẻ thành công!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Experience $experience)
    {
        if (Auth::id() !== $experience->user_id && !Auth::user()->isAdmin) {
            abort(403);
        }
        $experience->delete();
        return redirect()->route('experiences.index')->with('success', 'Đã xóa bài chia sẻ!');
    }

    // Trang quản lý cho admin
    public function adminIndex()
    {
        $experiences = Experience::orderByDesc('created_at')->paginate(15);
        return view('experiences.admin_index', compact('experiences'));
    }

    // Duyệt bài
    public function approve(Experience $experience)
    {
        $experience->status = 'approved';
        $experience->save();
        return back()->with('success', 'Đã duyệt bài chia sẻ!');
    }

    public function reject(Experience $experience)
    {
        $experience->status = 'rejected';
        $experience->save();
        return back()->with('success', 'Đã từ chối bài chia sẻ!');
    }
}
