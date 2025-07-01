<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $fillable = ['support_request_id', 'user_id', 'message', 'is_read'];

    public function supportRequest() {
        return $this->belongsTo(SupportRequest::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
