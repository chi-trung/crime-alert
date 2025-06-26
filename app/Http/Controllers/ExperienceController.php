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
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'name' => 'required|string|max:100',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $data = $request->only(['title', 'content', 'name']);
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }
        Experience::create($data);
        return redirect()->route('experiences.index')->with('success', 'Bài chia sẻ của bạn đã gửi và chờ duyệt!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Experience $experience)
    {
        if ($experience->status !== 'approved' && !(Auth::check() && Auth::user()->isAdmin)) {
            abort(403);
        }
        return view('experiences.show', compact('experience'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Experience $experience)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Experience $experience)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Experience $experience)
    {
        $experience->delete();
        return back()->with('success', 'Đã xóa bài chia sẻ!');
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
}
