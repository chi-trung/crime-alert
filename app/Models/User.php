<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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
            // Issue #191: `password_reset_tokens` is keyed by EMAIL (its
            // primary column), not user_id — so the #48 FK cascades never
            // touch it and no Eloquent relation exists to sweep it. A reset
            // token minted before account deletion therefore survives the
            // user, and because the broker resolves recipients by email, a
            // NEW account registered with the recycled address is matched by
            // that stale row: POST /reset-password with the dead user's old
            // token rewrites the newcomer's password and logs the takeover
            // in via the reset link. Same orphan-morph class as #57/#61,
            // with a security payload — delete the rows with the account.
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        // Issue #203: #191's twin. `password_reset_tokens` is keyed by email,
        // so the same takeover payload reopens whenever an account CHANGES
        // its address — the deleting hook above never fires because the user
        // survives. A reset token minted for alice@example.com keeps its row
        // after she moves to moved@example.com; the abandoned address is now
        // unclaimed, anyone can register it, and the broker resolves that
        // newcomer by email into alice's still-valid (TTL 60min) token —
        // POST /reset-password forceFills the newcomer's password. Sweep the
        // OLD address the moment it is left, in a saving hook rather than in
        // ProfileController so every future email-mutating path inherits the
        // guarantee, exactly like deleting does for #191. `exists` guards the
        // INSERT: on creation isDirty('email') is trivially true and there
        // is no previous address to sweep.
        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('email')) {
                DB::table('password_reset_tokens')
                    ->where('email', $user->getOriginal('email'))
                    ->delete();
            }
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
