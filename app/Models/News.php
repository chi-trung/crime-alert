<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image_url',
        'link',
        'published_at',
        'is_video',
    ];
}
