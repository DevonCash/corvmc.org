<?php

namespace CorvMC\Membership\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $inviter_id
 * @property string $email
 * @property string $token
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property string|null $message
 * @property array<array-key, mixed>|null $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $inviter_name
 * @property-read \App\Models\User|null $inviter
 *
 * @method static \Database\Factories\InvitationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Invitation forEmail(string $email)
 * @method static Builder<static>|Invitation from(\App\Models\User $user)
 * @method static Builder<static>|Invitation newModelQuery()
 * @method static Builder<static>|Invitation newQuery()
 * @method static Builder<static>|Invitation query()
 * @method static Builder<static>|Invitation whereCreatedAt($value)
 * @method static Builder<static>|Invitation whereData($value)
 * @method static Builder<static>|Invitation whereEmail($value)
 * @method static Builder<static>|Invitation whereExpiresAt($value)
 * @method static Builder<static>|Invitation whereId($value)
 * @method static Builder<static>|Invitation whereInviterId($value)
 * @method static Builder<static>|Invitation whereLastSentAt($value)
 * @method static Builder<static>|Invitation whereMessage($value)
 * @method static Builder<static>|Invitation whereToken($value)
 * @method static Builder<static>|Invitation whereUpdatedAt($value)
 * @method static Builder<static>|Invitation whereUsedAt($value)
 *
 * @mixin \Eloquent
 */
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
            if (empty($invitation->inviter_id) && Auth::check()) {
                $invitation->inviter_id = Auth::user()->id;
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

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function getInviterNameAttribute(): string
    {
        return $this->inviter?->name ?? 'System';
    }

    public function user(): ?User
    {
        return User::where('email', $this->email)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
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
    protected function forEmail(Builder $query, string $email): void
    {
        $query->where('email', $email);
    }

    #[Scope]
    protected function from(Builder $query, User $user): void
    {
        $query->where('inviter_id', '==', $user->id);
    }
}
