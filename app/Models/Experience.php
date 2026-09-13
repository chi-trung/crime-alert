<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
