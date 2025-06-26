<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;
use Illuminate\Support\Facades\Auth;

class AlertController extends Controller
{
    public function create()
    {
        return view('alerts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'location', 'type']);
        $data['user_id'] = Auth::id();
        $data['status'] = Auth::user()->isAdmin ? 'approved' : 'pending';
        $data['latitude'] = $request->input('latitude');
        $data['longitude'] = $request->input('longitude');

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('alerts', 'public');
            $data['image'] = $imagePath;
        }

        Alert::create($data);

        return redirect()->route('alerts.create')->with('success', 'Đăng cảnh báo thành công!');
    }

    public function index(Request $request)
    {
        $query = Alert::query();

        // Chỉ hiện cảnh báo đã duyệt
        $query->where('status', 'approved');

        // Lọc theo loại tội phạm
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        // Lọc theo trạng thái (nếu muốn cho admin/user xem các trạng thái khác)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Lọc theo vị trí
        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }
        // Tìm kiếm theo tiêu đề
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }
        // Lọc theo bán kính (radius)
        if ($request->filled('radius') && $request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radius = (float) $request->input('radius');
            $query->whereNotNull('latitude')->whereNotNull('longitude');
            $query->selectRaw('alerts.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        }

        $alerts = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return view('alerts.index', compact('alerts'));
    }

    public function adminIndex()
    {
        $alerts = Alert::orderByDesc('created_at')->paginate(15);
        return view('alerts.admin_index', compact('alerts'));
    }

    public function approve(Alert $alert)
    {
        $alert->status = 'approved';
        $alert->save();
        return back()->with('success', 'Đã duyệt cảnh báo thành công!');
    }

    public function reject(Alert $alert)
    {
        $alert->status = 'rejected';
        $alert->save();
        return back()->with('success', 'Đã từ chối cảnh báo!');
    }

    public function destroy(Alert $alert)
    {
        $alert->delete();
        return back()->with('success', 'Đã xóa cảnh báo!');
    }

    public function edit(Alert $alert)
    {
        // Chỉ cho admin hoặc chủ bài được sửa
        if (!auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }
        return view('alerts.edit', compact('alert'));
    }

    public function update(Request $request, Alert $alert)
    {
        if (!auth()->user()->isAdmin && $alert->user_id !== auth()->id()) {
            abort(403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $data = $request->only(['title', 'description', 'location', 'type']);
        $data['latitude'] = $request->input('latitude');
        $data['longitude'] = $request->input('longitude');
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('alerts', 'public');
            $data['image'] = $imagePath;
        }
        $alert->update($data);
        return redirect()->route('admin.alerts')->with('success', 'Cập nhật cảnh báo thành công!');
    }

    public function show(Alert $alert)
    {
        return view('alerts.show', compact('alert'));
    }

    public function mapView()
    {
        $alerts = Alert::where('status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
        return view('alerts.map', compact('alerts'));
    }
}
