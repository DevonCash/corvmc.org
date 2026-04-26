<?php

namespace CorvMC\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic invitation — a request for someone to respond to something.
 *
 * Covers band membership invitations, event RSVPs, rehearsal attendance,
 * and any future "ask someone to say yes or no" pattern.
 *
 * @property int $id
 * @property int|null $inviter_id
 * @property int $user_id
 * @property string $invitable_type
 * @property int $invitable_id
 * @property string $status
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $inviter
 * @property-read User $user
 * @property-read Model $invitable
 */
class Invitation extends Model
{
    use HasFactory;

    protected $table = 'support_invitations';

    protected $fillable = [
        'inviter_id',
        'user_id',
        'invitable_type',
        'invitable_id',
        'status',
        'data',
        'responded_at',
    ];

    protected $casts = [
        'data' => 'array',
        'responded_at' => 'datetime',
    ];

    protected static function newFactory(): \CorvMC\Support\Database\Factories\InvitationFactory
    {
        return \CorvMC\Support\Database\Factories\InvitationFactory::new();
    }

    // ── Relationships ────────────────────────────────────────────

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invitable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Status helpers ───────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }
}
