<?php

namespace App\Models;

use App\Support\BellSweeps;
use App\Support\DeferredFileUnlinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
            // bell rows whose url 404s. The matcher (closing-comma id form
            // so alert 1's sweep cannot eat alert 11's rows; post_type
            // discriminator so alert N never eats experience N's rows) lives
            // in BellSweeps::sweepPost since #311 — the destroy route's
            // #271-style post-delete fixed point sweeps through the SAME
            // method, so hook and sweep can never drift apart.
            BellSweeps::sweepPost($alert->id, 'alert');
            // Issue #289: unlink the path the ROW actually carries at delete
            // time, not the hydrating request's in-memory copy. The binding
            // that routes here (destroy, or ProfileController::destroy's
            // sweep, whose #266 lock covers only the users row) can be stale
            // — a rival replacement committed after hydration moved the
            // column to a fresh path, and the snapshot's unlink just deleted
            // a file the rival had ALREADY freed while the live file kept
            // zero referencing rows forever. A raw current read inside the
            // delete statement is the only value both races agree on;
            // builder reads fire no retrieved event, so #163-style probes
            // stay armable. Row already gone (racing double-delete) reads
            // null and frees nothing — correct, the winner unlinked it.
            // Issue #309: capture() instead of delete() — the WHICH is
            // unchanged (same current-read value), the WHEN now defers ONLY
            // when ProfileController::destroy() has armed the ledger around
            // its #266 transaction, where an in-transaction unlink survived
            // a late-sweep rollback (resurrected rows over dead files). The
            // drain right after the commit — or the discard when it throws,
            // because rolled-back rows KEEP their files — makes the account
            // teardown as atomic as its rows. Every other caller sees the
            // ledger unarmed and unlinks here, exactly as before.
            $live = DB::table('alerts')->where('id', $alert->id)->value('image');
            if ($live) {
                DeferredFileUnlinks::capture($live);
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
