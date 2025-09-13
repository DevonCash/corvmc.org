<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
