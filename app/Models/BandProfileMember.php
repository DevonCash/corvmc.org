<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BandProfileMember extends Model
{
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

    public function bandProfile(): BelongsTo
    {
        return $this->belongsTo(BandProfile::class);
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
}