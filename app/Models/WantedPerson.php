<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WantedPerson extends Model
{
    protected $table = 'wanted_people';
    protected $fillable = [
        'name',
        'birth_year',
        'address',
        'parents',
        'crime',
        'decision',
        'agency',
    ];
} 