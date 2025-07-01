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
        // Gửi notification hợp lý
        $currentUserId = auth()->id();
        $post = null;
        $postType = null;
        $postOwnerId = null;
        if ($request->filled('alert_id')) {
            $post = \App\Models\Alert::find($request->alert_id);
            $postType = 'alert';
            $postOwnerId = $post ? $post->user_id : null;
        } elseif ($request->filled('experience_id')) {
            $post = \App\Models\Experience::find($request->experience_id);
            $postType = 'experience';
            $postOwnerId = $post ? $post->user_id : null;
        }
        $parentComment = null;
        $parentOwnerId = null;
        if ($request->filled('parent_id')) {
            $parentComment = \App\Models\Comment::find($request->parent_id);
            $parentOwnerId = $parentComment ? $parentComment->user_id : null;
        }
        // Nếu là reply, chỉ gửi cho chủ comment cha (nếu khác người gửi)
        if ($parentComment && $parentOwnerId && $parentOwnerId != $currentUserId) {
            $parentComment->user->notify(new \App\Notifications\NewReplyOnComment($comment, $parentComment, $post, $postType));
        } elseif ($post && $postOwnerId && $postOwnerId != $currentUserId) {
            // Nếu là bình luận gốc, chỉ gửi cho chủ bài viết (nếu khác người gửi)
            $post->user->notify(new \App\Notifications\NewCommentOnPost($comment, $post, $postType));
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
