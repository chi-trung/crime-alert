<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->paginate(20);
        return view('notifications.index', compact('notifications'));
    }

    public function read($id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? route('notifications.index');
        return redirect($url);
    }

    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc!');
    }

    public function unreadAjax()
    {
        $unreadNotifications = auth()->user()->unreadNotifications()->take(10)->get();
        $data = $unreadNotifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'message' => $notification->data['message'] ?? 'Bạn có thông báo mới',
                'created_at' => $notification->created_at->diffForHumans(),
                'read_at' => $notification->read_at,
                'url' => $notification->data['url'] ?? null,
            ];
        });
        return response()->json([
            'count' => $unreadNotifications->count(),
            'notifications' => $data,
        ]);
    }
}
