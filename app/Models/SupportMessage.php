<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    // Issue #119: is_read was a dead column (dropped by migration
    // 2026_09_15) — zero setters and zero readers anywhere; read state lives
    // in the notifications table. Removing it from fillable also closes the
    // mass-assignment knob that could have created pre-marked-read rows.
    protected $fillable = ['support_request_id', 'user_id', 'message'];

    public function supportRequest()
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
