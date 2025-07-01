<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    protected $fillable = ['user_id', 'subject', 'status', 'admin_id'];

    public function messages() {
        return $this->hasMany(SupportMessage::class);
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
