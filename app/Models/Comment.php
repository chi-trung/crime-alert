<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Comment extends Model
{
    protected static function booted(): void
    {
        // Issue #57: morph likes have no FK to cascade them. Deleting a
        // parent also cascades its whole reply subtree at the DB level
        // (without firing their events), so sweep every descendant id.
        static::deleting(function (Comment $comment) {
            Like::where('likeable_type', self::class)
                ->whereIn('likeable_id', self::subtreeIds($comment->id))
                ->delete();
        });
    }

    /**
     * The comment and every reply beneath it, at any depth. The UI nests one
     * level, but the store endpoint accepts a parent_id for any comment, so
     * crafted posts can go deeper; the loop is two queries for real threads.
     */
    public static function subtreeIds(int $id): array
    {
        $ids = [$id];
        for ($frontier = $ids; $frontier !== []; $frontier = $next) {
            $next = self::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $next);
        }

        return $ids;
    }

    protected $fillable = [
        'alert_id',
        'experience_id',
        'user_id',
        'content',
        'parent_id',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        // Oldest-first is the thread order the view renders; declared on the
        // relation so eager-loaded replies keep it (issue #25).
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
