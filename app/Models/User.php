<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        // Issue #61: `notifications` is a morph relation (notifiable_type/id),
        // so no FK can cascade it and the Notifiable trait registers no
        // cleanup — every row the departing user had received would outlive
        // them, keyed to an id that no longer resolves. Same class of bug as
        // #57 (likes); this hook fires on any Eloquent user deletion, not
        // just the profile route.
        static::deleting(function (User $user) {
            $user->notifications()->delete();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * NOTE: isAdmin is deliberately NOT fillable — role changes must go
     * through forceFill()/explicit code paths (seeders, admin tooling),
     * never through request payloads.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'isAdmin' => 'boolean',
        ];
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }
}
