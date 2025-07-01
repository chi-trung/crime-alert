<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SupportRequest;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Auth;

class SupportRequestController extends Controller
{
    // Danh sách yêu cầu của user
    public function index() {
        $requests = SupportRequest::where('user_id', Auth::id())->latest()->get();
        return view('support.index', compact('requests'));
    }

    // Form gửi yêu cầu mới
    public function create() {
        return view('support.create');
    }

    // Lưu yêu cầu mới
    public function store(Request $request) {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
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
        $admins = \App\Models\User::where('isAdmin', true)->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\NewSupportRequest($supportRequest, Auth::user()));
        }
        return redirect()->route('support.show', $supportRequest)->with('success', 'Đã gửi yêu cầu trợ giúp!');
    }

    // Xem chi tiết và nhắn tin
    public function show(SupportRequest $supportRequest) {
        $messages = $supportRequest->messages()->with('user')->orderBy('created_at')->get();
        return view('support.show', compact('supportRequest', 'messages'));
    }

    // Gửi tin nhắn mới
    public function sendMessage(Request $request, SupportRequest $supportRequest) {
        $data = $request->validate([
            'message' => 'required|string',
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
            $supportRequest->user?->notify(new \App\Notifications\NewSupportMessage($supportRequest, $msg, $sender));
        } else {
            // User gửi, notify cho admin (nếu có admin nào, hoặc notify cho tất cả admin)
            $admins = \App\Models\User::where('isAdmin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\NewSupportMessage($supportRequest, $msg, $sender));
            }
        }
        return back();
    }

    // Danh sách yêu cầu cho admin
    public function adminIndex() {
        $requests = SupportRequest::latest()->get();
        return view('support.admin_index', compact('requests'));
    }

    // Đóng yêu cầu (admin)
    public function close(SupportRequest $supportRequest) {
        $supportRequest->update(['status' => 'closed']);
        return back()->with('success', 'Đã đóng yêu cầu!');
    }

    // Xóa yêu cầu hỗ trợ (admin)
    public function destroy(SupportRequest $supportRequest)
    {
        $supportRequest->delete();
        return back()->with('success', 'Đã xóa yêu cầu hỗ trợ!');
    }
}
