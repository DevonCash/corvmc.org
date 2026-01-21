<?php

namespace CorvMC\Membership\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $band_profile_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $invited_at
 * @property string $role
 * @property string|null $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $status
 * @property-read \App\Models\Band $band
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember active()
 * @method static \Database\Factories\BandMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember for(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember invited()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereBandProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereInvitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUserId($value)
 *
 * @mixin \Eloquent
 */
class BandMember extends Model
{
    use HasFactory;

    protected $table = 'band_profile_members';

    protected $fillable = [
        'band_profile_id',
        'user_id',
        'role',
        'position',
        'status',
        'invited_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class, 'band_profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    public function active($query)
    {
        return $query->where('status', 'active');
    }

    #[Scope]
    public function invited($query)
    {
        return $query->where('status', 'invited');
    }

    #[Scope]
    public function for($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
