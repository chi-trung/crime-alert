<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'alert_id' => 'required|exists:alerts,id',
            'content' => 'required|string|max:1000',
        ]);
        Comment::create([
            'alert_id' => $request->alert_id,
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);
        return back()->with('success', 'Bình luận thành công!');
    }

    public function edit(Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && !auth()->user()->isAdmin) {
            abort(403);
        }
        return view('comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && !auth()->user()->isAdmin) {
            abort(403);
        }
        $request->validate(['content' => 'required|string|max:1000']);
        $comment->update(['content' => $request->content]);
        return redirect()->route('alerts.show', $comment->alert_id)->with('success', 'Cập nhật bình luận thành công!');
    }

    public function destroy(Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && !auth()->user()->isAdmin) {
            abort(403);
        }
        $comment->delete();
        return back()->with('success', 'Đã xóa bình luận!');
    }
}
