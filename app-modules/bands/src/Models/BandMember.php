<?php

namespace CorvMC\Bands\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an active band membership (the pivot between Band and User).
 *
 * Invitation state is no longer tracked here — pending invitations live in
 * support_invitations via the polymorphic Invitation model.
 *
 * @property int $id
 * @property int $band_profile_id
 * @property int $user_id
 * @property string $role
 * @property string|null $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Band $band
 * @property-read User $user
 *
 * @method static \Database\Factories\BandMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember for(User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereBandProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BandMember whereUserId($value)
 *
 * @mixin \Eloquent
 */
class BandMember extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Database\Factories\BandMemberFactory
    {
        return \Database\Factories\BandMemberFactory::new();
    }

    protected $table = 'band_profile_members';

    protected $fillable = [
        'band_profile_id',
        'user_id',
        'role',
        'position',
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
    public function for($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
