<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;
use App\Models\Experience;
use App\Models\Like;
use App\Models\Comment;

class LikeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:alert,experience,comment',
            'id' => 'required|integer',
        ]);
        $user = auth()->user();
        $type = $request->type;
        $id = $request->id;
        if ($type === 'alert') {
            $model = Alert::findOrFail($id);
        } elseif ($type === 'experience') {
            $model = Experience::findOrFail($id);
        } else {
            $model = Comment::findOrFail($id);
        }
        if (!$model->likes()->where('user_id', $user->id)->exists()) {
            $model->likes()->create(['user_id' => $user->id]);
            if ($type === 'comment' && $model->user_id != $user->id) {
                $post = $model->alert_id ? Alert::find($model->alert_id) : Experience::find($model->experience_id);
                $postType = $model->alert_id ? 'alert' : 'experience';
                $model->user->notify(new \App\Notifications\LikeCommentNotification($user, $model, $post, $postType));
            }
            if (($type === 'alert' || $type === 'experience') && $model->user_id != $user->id) {
                $model->user->notify(new \App\Notifications\LikePostNotification($user, $model, $type));
            }
        }
        $count = $model->likes()->count();
        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }
        return back();
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'type' => 'required|in:alert,experience,comment',
            'id' => 'required|integer',
        ]);
        $user = auth()->user();
        $type = $request->type;
        $id = $request->id;
        if ($type === 'alert') {
            $model = Alert::findOrFail($id);
        } elseif ($type === 'experience') {
            $model = Experience::findOrFail($id);
        } else {
            $model = Comment::findOrFail($id);
        }
        $model->likes()->where('user_id', $user->id)->delete();
        $count = $model->likes()->count();
        if ($request->expectsJson() || $request->isJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'count' => $count]);
        }
        return back();
    }
} 