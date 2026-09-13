<?php

namespace App\Http\Controllers;

class NotificationController extends Controller
{
    public function index()
    {
        // Issue #81: the framework relation ends in a plain ->latest()
        // (created_at desc only), so same-second notifications had no
        // deterministic order across pages. Id tiebreak, #75's idiom.
        $notifications = auth()->user()->notifications()->orderByDesc('id')->paginate(20);

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
        // Issue #69: the badge used to report this take(10) list's size, so
        // it froze at "10" for anyone with more unread notifications. The
        // dropdown is a 10-item preview, but the badge shows the true total;
        // count() on the relation is a second, tiny SELECT.
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
            'count' => auth()->user()->unreadNotifications()->count(),
            'notifications' => $data,
        ]);
    }
}
