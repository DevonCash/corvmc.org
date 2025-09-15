<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $band_profile_id
 * @property int|null $user_id
 * @property string|null $name
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $invited_at
 * @property string $role
 * @property string|null $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Band $band
 * @property-read string|null $avatar_url
 * @property-read string $display_name
 * @property-read bool $is_cmc_member
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember declined()
 * @method static \Database\Factories\BandMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember for(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember inactive()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember invited()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereBandProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereInvitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUserId($value)
 * @mixin \Eloquent
 */
class BandMember extends Model
{
    use HasFactory;
    protected $table = 'band_profile_members';

    protected $fillable = [
        'band_profile_id',
        'user_id',
        'name',
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

    // Get display name - either from pivot name or user name
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->user?->name ?? 'Unknown Member');
    }

    // Check if this is a CMC member (has user_id)
    public function getIsCmcMemberAttribute(): bool
    {
        return !is_null($this->user_id);
    }

    // Get avatar URL from user if available
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->user?->getFilamentAvatarUrl();
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
    public function declined($query)
    {
        return $query->where('status', 'declined');
    }

    #[Scope]
    public function inactive($query)
    {
        return $query->where('status', 'inactive');
    }

    #[Scope]
    public function for($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
