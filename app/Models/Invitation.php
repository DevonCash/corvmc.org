<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Invitation extends Model
{

    use HasFactory;
    protected $fillable = [
        'inviter_id',
        'message',
        'email',
        'token',
        'expires_at',
        'used_at',
        'last_sent_at',
        'data',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = bin2hex(random_bytes(16));
            }
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
            if (empty($invitation->message)) {
                $invitation->message = 'Join me at Corvallis Music Collective!';
            }
            if (empty($invitation->inviter_id)) {
                $invitation->inviter_id = Auth::user()?->id;
            }
        });

        static::addGlobalScope('unexpired', function ($query) {
            $query->where('expires_at', '>', now());
        });

        static::addGlobalScope('unused', function ($query) {
            $query->whereNull('used_at');
        });

        static::addGlobalScope('sent', function ($query) {
            $query->whereNotNull('last_sent_at');
        });
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function user()
    {
        return User::where('email', $this->email)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function markAsUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }

    public function markAsSent(): void
    {
        $this->last_sent_at = now();
        $this->save();
    }

    #[Scope]
    protected function forEmail(Builder $query, string $email)
    {
        $query->where('email', $email);
    }

    #[Scope]
    protected function from(Builder $query, User $user)
    {
        $query->where('inviter_id', '==', $user->id);
    }
}
