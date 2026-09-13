<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportRequestController extends Controller
{
    /**
     * Only administrators may run the admin queue actions. The routes
     * already sit behind the ['auth','admin'] group (AdminMiddleware 403s
     * non-admins end-to-end — issue #97's HTTP probe passes with or without
     * this), but close()/destroy() mutate state and must not depend on route
     * wiring alone: every sibling mutating action in this app carries its
     * own check. abort_unless keeps the no-session CLI shape out — an
     * unauthenticated direct call 403s the same as a signed-in non-admin.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin, 403);
    }

    /**
     * Only the thread owner and administrators may view/participate.
     */
    private function authorizeViewer(SupportRequest $supportRequest): void
    {
        abort_unless(
            Auth::check() && (Auth::user()->isAdmin || $supportRequest->user_id === Auth::id()),
            403
        );
    }

    // Danh sách yêu cầu của user
    public function index()
    {
        // Issue #75: unbounded ->get() of every request the user ever filed;
        // the id tiebreak keeps page boundaries stable when requests share a
        // created_at second (latest() alone orders by created_at only).
        $requests = SupportRequest::where('user_id', Auth::id())
            ->latest()
            ->orderByDesc('id')
            ->paginate(10);

        return view('support.index', compact('requests'));
    }

    // Form gửi yêu cầu mới
    public function create()
    {
        return view('support.create');
    }

    // Lưu yêu cầu mới
    public function store(Request $request)
    {
        // Issue #129: same gate as the alert/experience/comment stores —
        // this endpoint fans NewSupportRequest out to every admin with user
        // text in the payload, and unverified accounts (any fake email passes
        // registration) must not get the cheapest notification-spam primitive
        // in the app.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để liên hệ hỗ trợ.');
        }
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            // Issue #39: TEXT column, unbounded like #37 — bound it.
            'message' => 'required|string|max:5000',
        ]);
        $supportRequest = SupportRequest::create([
            'user_id' => Auth::id(),
            'subject' => $data['subject'],
        ]);
        SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'user_id' => Auth::id(),
            'message' => $data['message'],
        ]);
        // Gửi notification cho admin
        $admins = User::where('isAdmin', true)->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewSupportRequest($supportRequest, Auth::user()));
        }

        return redirect()->route('support.show', $supportRequest)->with('success', 'Đã gửi yêu cầu trợ giúp!');
    }

    // Xem chi tiết và nhắn tin
    public function show(SupportRequest $supportRequest)
    {
        $this->authorizeViewer($supportRequest);
        // Issue #89: id ASC tiebreak — chat order is oldest-first, and two
        // messages in the same second must not swap between renders (the
        // AJAX feed on this list is polled, so the flicker was live).
        $messages = $supportRequest->messages()->with('user')->orderBy('created_at')->orderBy('id')->get();

        return view('support.show', compact('supportRequest', 'messages'));
    }

    // Gửi tin nhắn mới
    public function sendMessage(Request $request, SupportRequest $supportRequest)
    {
        // Issue #129: replies ride NewSupportMessage to the counterpart (or
        // every admin) just like store() — same verification gate, checked
        // before authorizeViewer so the guard reads as the method's first
        // contract.
        if (! Auth::user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để liên hệ hỗ trợ.');
        }
        $this->authorizeViewer($supportRequest);
        if ($supportRequest->status !== 'open') {
            return back()->with('error', 'Yêu cầu đã đóng, không thể gửi thêm tin nhắn.');
        }
        $data = $request->validate([
            // Issue #39: same bound as store().
            'message' => 'required|string|max:5000',
        ]);
        $msg = SupportMessage::create([
            'support_request_id' => $supportRequest->id,
            'user_id' => Auth::id(),
            'message' => $data['message'],
        ]);
        // Gửi notification cho đối phương
        $sender = Auth::user();
        if ($sender->isAdmin) {
            // Admin gửi, notify cho user
            $supportRequest->user?->notify(new NewSupportMessage($supportRequest, $msg, $sender));
        } else {
            // User gửi, notify cho admin (nếu có admin nào, hoặc notify cho tất cả admin)
            $admins = User::where('isAdmin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new NewSupportMessage($supportRequest, $msg, $sender));
            }
        }

        return back();
    }

    // Danh sách yêu cầu cho admin
    public function adminIndex()
    {
        // Issue #75: same class as #67/#71 — the whole request table loaded
        // on every admin page view. Paginate like every other list.
        $requests = SupportRequest::with('user')
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return view('support.admin_index', compact('requests'));
    }

    // Đóng yêu cầu (admin)
    public function close(SupportRequest $supportRequest)
    {
        // Issue #97: defense in depth — the route group already 403s, but a
        // state-changing action must assert adminship itself.
        $this->authorizeAdmin();
        // Issue #98: closing an already-closed thread rewrote the same
        // status and reported success — a misleading no-op on every repeat
        // click. The sibling sendMessage() already treats closed as a
        // distinct state; close() now does too. The info bag is live
        // (layouts/app renders session('info')).
        if ($supportRequest->status === 'closed') {
            return back()->with('info', 'Yêu cầu này đã được đóng trước đó.');
        }
        $supportRequest->update(['status' => 'closed']);

        return back()->with('success', 'Đã đóng yêu cầu!');
    }

    // Xóa yêu cầu hỗ trợ (admin)
    public function destroy(SupportRequest $supportRequest)
    {
        // Issue #97: same in-method assertion as close() above.
        $this->authorizeAdmin();
        $supportRequest->delete();

        return back()->with('success', 'Đã xóa yêu cầu hỗ trợ!');
    }

    // API trả về danh sách tin nhắn dạng JSON
    public function messagesAjax(SupportRequest $supportRequest)
    {
        $this->authorizeViewer($supportRequest);
        // Issue #89: id ASC tiebreak (see show()) — this is the polled feed.
        $messages = $supportRequest->messages()->with('user')->orderBy('created_at')->orderBy('id')->get();
        $result = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'user' => $msg->user ? $msg->user->name : 'Ẩn danh',
                'is_me' => $msg->user_id == auth()->id(),
                'is_admin' => (bool) ($msg->user?->isAdmin ?? false),
                'content' => $msg->message,
                'created_at' => $msg->created_at->format('H:i d/m/Y'),
            ];
        });

        return response()->json(['messages' => $result]);
    }
}
