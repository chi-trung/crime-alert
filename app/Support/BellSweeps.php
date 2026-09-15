<?php

namespace App\Support;

use App\Notifications\LikeCommentNotification;
use App\Notifications\LikePostNotification;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewPostNotification;
use App\Notifications\NewPostPendingApprovalNotification;
use App\Notifications\NewReplyOnComment;
use Illuminate\Support\Facades\DB;

/**
 * Issue #311: the post-delete fixed-point matcher used by the
 * Alert/Experience/Comment destroy routes. Same structural premise as
 * #102/#121: these notification rows point at a post (or comment) only
 * inside their JSON data payload — the morph notifiable_* pair keys the
 * RECIPIENT, so no FK ever cascades them. The deleting() hooks (#121)
 * sweep them, but a hook runs BEFORE the DELETE statement acquires the
 * row's X lock: a fan-out transaction holding that lock (#153's
 * lockForUpdate target read) can still commit its bell after the sweep
 * has already answered. #271 closed exactly that window for the support
 * thread destroy with a post-delete fixed-point sweep; this helper is
 * the alert/experience/comment mirror, kept in one place because the
 * type list and the comma-delimited id matcher (#102: "post_id":7,% must
 * never eat "post_id":17) are load-bearing and were duplicated three
 * times already across the hooks. The hook lists (#121) stay untouched
 * in the models — this class must carry the same six post classes and
 * three comment classes, which DestroyOrphanBellWindowTest pins by
 * source comparison.
 */
class BellSweeps
{
    /** The six classes whose payload carries post_id/post_type (see #121). */
    public const POST_TYPES = [
        NewPostNotification::class,
        NewPostPendingApprovalNotification::class,
        LikePostNotification::class,
        NewCommentOnPost::class,
        NewReplyOnComment::class,
        LikeCommentNotification::class,
    ];

    /** The three classes whose payload carries comment_id/reply_id/parent_comment_id (see #121). */
    public const COMMENT_TYPES = [
        NewCommentOnPost::class,
        NewReplyOnComment::class,
        LikeCommentNotification::class,
    ];

    /**
     * Delete every post-bell for one post id of one post type; returns the
     * affected-row count so the caller can loop to a fixed point. The
     * post_type discriminator keeps alert N from eating experience N's
     * rows (ids collide across the two tables — #121's exact note).
     */
    public static function sweepPost(int $postId, string $postType): int
    {
        return DB::table('notifications')
            ->whereIn('type', self::POST_TYPES)
            ->where('data', 'like', '%"post_id":'.$postId.',%')
            ->where('data', 'like', '%"post_type":"'.$postType.'"%')
            ->delete();
    }

    /**
     * Delete every comment-bell keyed on any of the given comment ids —
     * each id matched through all three payload keys the classes use
     * (comment_id, reply_id, parent_comment_id), closing-comma form per
     * #102. Empty id list is never a match-everything orWhere group;
     * it deletes nothing (the Comment.php guard, same reasoning).
     */
    public static function sweepComments(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::table('notifications')
            ->whereIn('type', self::COMMENT_TYPES)
            ->where(function ($q) use ($ids): void {
                foreach ($ids as $id) {
                    $q->orWhere('data', 'like', '%"comment_id":'.$id.',%')
                        ->orWhere('data', 'like', '%"reply_id":'.$id.',%')
                        ->orWhere('data', 'like', '%"parent_comment_id":'.$id.',%');
                }
            })
            ->delete();
    }
}
