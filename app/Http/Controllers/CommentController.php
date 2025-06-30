<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('Comment request:', $request->all());
        $request->validate([
            'content' => 'required|string|max:1000',
            'alert_id' => 'nullable|exists:alerts,id',
            'experience_id' => 'nullable|exists:experiences,id',
        ]);
        $data = [
            'user_id' => auth()->id(),
            'content' => $request->content,
        ];
        if ($request->filled('parent_id')) {
            $data['parent_id'] = $request->parent_id;
        }
        if ($request->filled('alert_id')) {
            $data['alert_id'] = $request->alert_id;
        }
        if ($request->filled('experience_id')) {
            $data['experience_id'] = $request->experience_id;
        }
        $comment = \App\Models\Comment::create($data);
        // Gửi notification cho chủ bài viết
        if ($request->filled('alert_id')) {
            $alert = \App\Models\Alert::find($request->alert_id);
            if ($alert && $alert->user_id != auth()->id()) {
                $alert->user->notify(new \App\Notifications\NewCommentOnPost($comment, $alert, 'alert'));
            }
        }
        if ($request->filled('experience_id')) {
            $exp = \App\Models\Experience::find($request->experience_id);
            if ($exp && $exp->user_id != auth()->id()) {
                $exp->user->notify(new \App\Notifications\NewCommentOnPost($comment, $exp, 'experience'));
            }
        }
        if ($request->filled('parent_id')) {
            $parentComment = \App\Models\Comment::find($request->parent_id);
            if ($parentComment && $parentComment->user_id != auth()->id()) {
                $post = $comment->alert_id ? $comment->alert : $comment->experience;
                $postType = $comment->alert_id ? 'alert' : 'experience';
                $parentComment->user->notify(new \App\Notifications\NewReplyOnComment($comment, $parentComment, $post, $postType));
            }
        }
        if ($request->filled('experience_id')) {
            return redirect()->route('experiences.show', $request->experience_id)->with('success', 'Bình luận đã được gửi!');
        }
        return back()->with('success', 'Bình luận đã được gửi!');
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
        if ($comment->experience_id) {
            return redirect()->route('experiences.show', $comment->experience_id)->with('success', 'Cập nhật bình luận thành công!');
        }
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
