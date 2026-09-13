<?php

namespace App\Models;

use App\Notifications\NewSupportMessage;
use App\Notifications\NewSupportRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupportRequest extends Model
{
    protected static function booted(): void
    {
        // Issue #102: NewSupportRequest/NewSupportMessage rows point at the
        // thread only inside their JSON data payload (support_request_id) —
        // the morph notifiable_* pair keys the recipient, not the thread, so
        // no FK can cascade them (same structural reason as #57/#61).
        // Without this hook every deleted thread left orphan rows whose
        // link 404s. The id is matched as `"support_request_id":N` followed
        // by a comma — the payload always continues with ,"support_subject"
        // next (both notification classes), so thread 1's sweep cannot eat
        // thread 11's rows. The closing-comma form works on both sqlite and
        // mysql LIKE. Scoped to the two notification types this feature
        // emits.
        static::deleting(function (SupportRequest $request) {
            DB::table('notifications')
                ->whereIn('type', [
                    NewSupportRequest::class,
                    NewSupportMessage::class,
                ])
                ->where('data', 'like', '%"support_request_id":'.$request->id.',%')
                ->delete();
        });
    }

    // Issue #108: admin_id was a dead column (dropped by migration
    // 2026_09_14) — no write site ever set it and the admin() relation had
    // zero callers (admins are told apart via messages.user.isAdmin).
    protected $fillable = ['user_id', 'subject', 'status'];

    public function messages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
