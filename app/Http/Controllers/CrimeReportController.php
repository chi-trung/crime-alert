<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CrimeReport;
use Illuminate\Support\Facades\Auth;

class CrimeReportController extends Controller
{
    public function index()
    {
        $reports = CrimeReport::where('status', 'approved')->latest()->paginate(10);
        return view('crime_reports.index', compact('reports'));
    }

    public function create()
    {
        return view('crime_reports.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'reported_at' => 'nullable|date',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('crime_reports', 'public');
        }

        CrimeReport::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'location' => $request->location,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'image' => $imagePath,
            'reported_at' => $request->reported_at ?? now(),
            'status' => 'pending',
        ]);

        return redirect()->route('crime-reports.index')->with('success', 'Gửi cảnh báo thành công, chờ admin duyệt!');
    }

    public function adminIndex()
    {
        $reports = CrimeReport::latest()->paginate(15);
        return view('crime_reports.admin_index', compact('reports'));
    }

    public function approve($id)
    {
        $report = CrimeReport::findOrFail($id);
        $report->status = 'approved';
        $report->save();
        return back()->with('success', 'Đã duyệt cảnh báo!');
    }

    public function reject($id)
    {
        $report = CrimeReport::findOrFail($id);
        $report->status = 'rejected';
        $report->save();
        return back()->with('success', 'Đã từ chối cảnh báo!');
    }

    public function destroy($id)
    {
        $report = CrimeReport::findOrFail($id);
        $report->delete();
        return back()->with('success', 'Đã xóa cảnh báo!');
    }
}
