<?php

namespace App\Models;

use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewReplyOnComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Alert extends Model
{
    protected static function booted(): void
    {
        // Issue #53: removing the row must also remove its uploaded image.
        // A model event (not the controllers) so the admin destroy route and
        // any bulk delete pay the same cost. DB-level cascades from user
        // deletion do NOT fire this — that path is handled explicitly in
        // ProfileController::destroy.
        static::deleting(function (Alert $alert) {
            // Issue #57: likes is a morph table, so no FK can cascade it —
            // without this every delete leaves the post's like rows behind.
            // The alert's comments also vanish by FK cascade (no events
            // fired), and since replies inherit alert_id in
            // CommentController::store, this covers the whole thread tree.
            $alert->likes()->delete();
            Like::where('likeable_type', Comment::class)
                ->whereIn('likeable_id', Comment::where('alert_id', $alert->id)->pluck('id'))
                ->delete();
            // Issue #121: the six post notification classes point at this
            // post only inside their JSON data payload (post_id + post_type) —
            // the morph notifiable_* pair keys the recipient, so no FK
            // cascades them (same structural reason as #57/#61, and the #102
            // support-thread sweep). Without this every deleted alert leaves
            // bell rows whose url 404s. The id match uses the closing-comma
            // form — every payload continues past post_id — so alert 1's
            // sweep cannot eat alert 11's rows; post_type is matched too so
            // alert N never eats experience N's rows.
            DB::table('notifications')
                ->whereIn('type', [
                    NewPostNotification::class,
                    NewPostPendingApprovalNotification::class,
                    LikePostNotification::class,
                    NewCommentOnPost::class,
                    NewReplyOnComment::class,
                    LikeCommentNotification::class,
                ])
                ->where('data', 'like', '%"post_id":'.$alert->id.',%')
                ->where('data', 'like', '%"post_type":"alert"%')
                ->delete();
            if ($alert->image) {
                Storage::disk('public')->delete($alert->image);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'image',
        'status',
        'type',
        'latitude',
        'longitude',
    ];

    public function user(): BelongsTo
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
