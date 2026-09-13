<?php

namespace App\Models;

use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewReplyOnComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Experience extends Model
{
    protected static function booted(): void
    {
        // Issue #53: same contract as Alert — the avatar file must not
        // outlive the row.
        static::deleting(function (Experience $experience) {
            // Issue #57: morph likes have no FK, so they need the same
            // explicit sweep as Alert. Replies inherit experience_id, so
            // this covers comment threads too.
            $experience->likes()->delete();
            Like::where('likeable_type', Comment::class)
                ->whereIn('likeable_id', Comment::where('experience_id', $experience->id)->pluck('id'))
                ->delete();
            // Issue #121: same notification sweep as Alert::deleting, scoped
            // to post_type experience so experience N never eats alert N's
            // rows (ids collide across the two tables).
            DB::table('notifications')
                ->whereIn('type', [
                    NewPostNotification::class,
                    NewPostPendingApprovalNotification::class,
                    LikePostNotification::class,
                    NewCommentOnPost::class,
                    NewReplyOnComment::class,
                    LikeCommentNotification::class,
                ])
                ->where('data', 'like', '%"post_id":'.$experience->id.',%')
                ->where('data', 'like', '%"post_type":"experience"%')
                ->delete();
            if ($experience->avatar) {
                Storage::disk('public')->delete($experience->avatar);
            }
        });
    }

    protected $fillable = [
        'user_id', 'name', 'title', 'content', 'avatar', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
