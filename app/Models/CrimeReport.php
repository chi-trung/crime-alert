<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrimeReport extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'latitude',
        'longitude',
        'image',
        'reported_at',
        'status',
    ];

    //
}
