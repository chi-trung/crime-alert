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
        // Issue #110: data['url'] rode straight into redirect() unvalidated —
        // any row with an external url turned a bell click into an open
        // redirect. Only same-app targets pass through; anything else (and a
        // missing url, as before) falls back to the notification list. A
        // relative path is local by construction; an absolute URL must match
        // the app host, and protocol-relative //evil forms fail the host
        // comparison instead of inheriting the request scheme.
        $url = $notification->data['url'] ?? null;
        if (! is_string($url) || ! $this->isLocalUrl($url)) {
            $url = route('notifications.index');
        }

        return redirect($url);
    }

    private function isLocalUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        return mb_strtolower($host) === mb_strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
    }

    public function readAll()
    {
        // Issue #93: the magic attribute loaded the whole unread set and
        // markAsRead() on the collection proxied save() per row — one
        // SELECT plus N UPDATEs. The relation's query builder scopes the
        // same rows (unread() ends in whereNull('read_at')), so one
        // bulk UPDATE does the job; notifications rows fire no model-event
        // listeners in this app, so behavior is unchanged.
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc!');
    }

    public function unreadAjax()
    {
        // Issue #89: the framework relation ends in ->latest() (created_at
        // desc only), so a same-second burst truncated to an arbitrary 10 —
        // the #81 page fix never covered this dropdown preview.
        $unreadNotifications = auth()->user()->unreadNotifications()->orderByDesc('id')->take(10)->get();
        // Issue #69: the badge used to report this take(10) list's size, so
        // it froze at "10" for anyone with more unread notifications. The
        // dropdown is a 10-item preview, but the badge shows the true total;
        // count() on the relation is a second, tiny SELECT.
        // Issue #113: the feed used to return the raw data['url'] and the
        // dropdown assigned it straight to a.href — clicks navigated to the
        // target directly, bypassing read()'s #110 isLocalUrl gate (and a
        // javascript: payload would execute as a href). Rows now link to
        // the read route, which marks read and validates server-side; the
        // raw url never leaves the server.
        $data = $unreadNotifications->map(function ($notification) {
            return [
                'id' => $notification->id,
                'message' => $notification->data['message'] ?? 'Bạn có thông báo mới',
                'created_at' => $notification->created_at->diffForHumans(),
                'read_at' => $notification->read_at,
                'read_url' => route('notifications.read', $notification->id),
            ];
        });

        return response()->json([
            'count' => auth()->user()->unreadNotifications()->count(),
            'notifications' => $data,
        ]);
    }
}
