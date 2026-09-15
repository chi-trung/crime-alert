<?php

namespace App\Models;

use App\Support\BellSweeps;
use App\Support\DeferredFileUnlinks;
use Illuminate\Database\Eloquent\Model;

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
            // rows (ids collide across the two tables). Matcher + type list
            // shared through BellSweeps::sweepPost since #311.
            BellSweeps::sweepPost($experience->id, 'experience');
            // Issue #309: capture() not delete() — same #289 reasoning as
            // Alert::deleting; the unlink defers only inside
            // ProfileController::destroy()'s armed ledger, everywhere else
            // capture() unlinks immediately. Note the value here is the
            // in-memory avatar: this hook predates #289 and no test drives a
            // stale-binding race through it; changing WHICH path is out of
            // scope for #309 (that would be an Experience-side #289).
            if ($experience->avatar) {
                DeferredFileUnlinks::capture($experience->avatar);
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
