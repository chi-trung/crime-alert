<?php

namespace App\Models;

use App\Notifications\LikeCommentNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewReplyOnComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

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
            // Issue #121: the three comment-bearing notification classes
            // point at comments only inside their JSON payload — same orphan
            // shape as the likes above. Sweep over the whole subtree (the FK
            // cascade drops child replies without events): comment_id and
            // reply_id/parent_comment_id each use the closing-comma exact-id
            // form so comment 1 never eats comment 11's rows (#102's idiom).
            $subtree = self::subtreeIds($comment->id);
            $query = DB::table('notifications')
                ->whereIn('type', [
                    NewCommentOnPost::class,
                    NewReplyOnComment::class,
                    LikeCommentNotification::class,
                ])
                ->where(function ($q) use ($subtree) {
                    foreach ($subtree as $id) {
                        $q->orWhere('data', 'like', '%"comment_id":'.$id.',%')
                            ->orWhere('data', 'like', '%"reply_id":'.$id.',%')
                            ->orWhere('data', 'like', '%"parent_comment_id":'.$id.',%');
                    }
                });
            // An empty subtree is impossible (it always contains $comment->id),
            // but an empty orWhere group would match everything — guard anyway.
            if ($subtree !== []) {
                $query->delete();
            }
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
        // Issue #89: id ASC tiebreak — replies sent in the same second would
        // otherwise render in scan order, eagerly loaded ones included.
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
