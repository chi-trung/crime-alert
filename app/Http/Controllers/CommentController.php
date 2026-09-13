<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Comment;
use App\Models\Experience;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewReplyOnComment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        if (! auth()->user()->hasVerifiedEmail()) {
            return redirect()->back()->with('error', 'Bạn cần xác thực email để bình luận hoặc trả lời bình luận.');
        }
        $request->validate([
            'content' => 'required|string|max:1000',
            // A top-level comment needs a post; a reply inherits one from
            // its parent (checked below), so only requires the field when
            // neither sibling is present.
            'alert_id' => 'nullable|required_without_all:experience_id,parent_id|exists:alerts,id',
            'experience_id' => 'nullable|exists:experiences,id',
            // Issue #35: parent used to be stored unchecked — no existence
            // rule, and nothing bound it to the submitted post.
            'parent_id' => 'nullable|exists:comments,id',
        ]);
        $data = [
            'user_id' => auth()->id(),
            'content' => $request->content,
        ];
        if ($request->filled('parent_id')) {
            $parent = Comment::findOrFail($request->parent_id);
            // A reply hangs off its parent thread, so the parent defines the
            // owning post. The client's own post id may only agree with it;
            // anything else is a crafted cross-thread injection attempt.
            if (($request->filled('alert_id') && (int) $request->alert_id !== (int) $parent->alert_id)
                || ($request->filled('experience_id') && (int) $request->experience_id !== (int) $parent->experience_id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Bình luận cha không thuộc bài viết này.',
                ]);
            }
            $data['parent_id'] = $parent->id;
            if ($parent->alert_id) {
                $data['alert_id'] = $parent->alert_id;
            }
            if ($parent->experience_id) {
                $data['experience_id'] = $parent->experience_id;
            }
        } elseif ($request->filled('alert_id')) {
            $data['alert_id'] = $request->alert_id;
        } else {
            $data['experience_id'] = $request->experience_id;
        }
        // Issue #95: the blades only offer the form on approved posts and
        // show() 403s strangers elsewhere (#20), but this endpoint accepted
        // comments on pending/rejected posts — a verified user could spam
        // an author's feed through a rejected post. Replies inherit their
        // parent's post above, so checking the resolved target covers both
        // branches with one gate.
        $target = isset($data['alert_id'])
            ? Alert::find($data['alert_id'])
            : Experience::find($data['experience_id']);
        abort_unless($target && $target->status === 'approved', 403);
        $comment = Comment::create($data);
        // Gửi notification hợp lý
        $currentUserId = auth()->id();
        $post = null;
        $postType = null;
        $postOwnerId = null;
        if ($comment->alert_id) {
            $post = Alert::find($comment->alert_id);
            $postType = 'alert';
            $postOwnerId = $post ? $post->user_id : null;
        } elseif ($comment->experience_id) {
            $post = Experience::find($comment->experience_id);
            $postType = 'experience';
            $postOwnerId = $post ? $post->user_id : null;
        }
        $parentComment = $parent ?? null;
        $parentOwnerId = $parentComment ? $parentComment->user_id : null;
        // Nếu là reply, chỉ gửi cho chủ comment cha (nếu khác người gửi)
        if ($parentComment && $parentOwnerId && $parentOwnerId != $currentUserId) {
            $parentComment->user->notify(new NewReplyOnComment($comment, $parentComment, $post, $postType));
        } elseif ($post && $postOwnerId && $postOwnerId != $currentUserId) {
            // Nếu là bình luận gốc, chỉ gửi cho chủ bài viết (nếu khác người gửi)
            $post->user->notify(new NewCommentOnPost($comment, $post, $postType));
        }
        if ($comment->experience_id) {
            return redirect()->route('experiences.show', $comment->experience_id)->with('success', 'Bình luận đã được gửi!');
        }

        return back()->with('success', 'Bình luận đã được gửi!');
    }

    public function edit(Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
            abort(403);
        }

        return view('comments.edit', compact('comment'));
    }

    public function update(Request $request, Comment $comment)
    {
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
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
        if (auth()->id() !== $comment->user_id && ! auth()->user()->isAdmin) {
            abort(403);
        }
        $comment->delete();

        return back()->with('success', 'Đã xóa bình luận!');
    }
}
