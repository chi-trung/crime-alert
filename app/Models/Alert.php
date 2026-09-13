<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
