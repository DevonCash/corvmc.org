<?php

namespace App\Models;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelFlags\Models\Concerns\HasFlags;
use Spatie\Tags\HasTags;

/**
 * Represents a band profile in the application.
 * It includes details about the band's name, bio, links, and contact information.
 * The band can have multiple members and exactly one owner.
 */

class BandProfile extends Model implements HasMedia
{
    use HasFactory, HasFlags, HasTags, InteractsWithMedia;

    protected $fillable = [
        'name',
        'bio',
        'links',
        'contact',
        'hometown',
        'owner_id',
        'visibility',
    ];

    protected $casts = [
        'links' => 'array',
        'contact' => 'array',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'band_profile_members')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->withTimestamps();
    }

    public function activeMembers()
    {
        return $this->belongsToMany(User::class, 'band_profile_members')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    public function pendingInvitations()
    {
        return $this->belongsToMany(User::class, 'band_profile_members')
            ->withPivot('role', 'position', 'name', 'status', 'invited_at')
            ->wherePivot('status', 'invited')
            ->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function getGenresAttribute()
    {
        return $this->tagsWithType('genre');
    }

    public function getInfluencesAttribute()
    {
        return $this->tagsWithType('influence');
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->hasMedia('avatar')) {
            return $this->getFirstMediaUrl('avatar');
        }

        return null;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->performOnCollections('avatar');
    }

    public function getAvatarThumbUrlAttribute()
    {
        if ($this->hasMedia('avatar')) {
            return $this->getFirstMediaUrl('avatar', 'thumb');
        }

        return $this->avatar_url;
    }

    /**
     * Check if a user is the owner of this band.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Check if a user is a member of this band.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->wherePivot('user_id', $user->id)->exists();
    }

    /**
     * Check if a user is an admin of this band.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Get a user's role in this band.
     */
    public function getUserRole(User $user): ?string
    {
        if ($this->owner_id === $user->id) {
            return 'owner';
        }

        $membership = $this->members()
            ->wherePivot('user_id', $user->id)
            ->first();

        return $membership?->pivot->role;
    }

    /**
     * Get a user's position in this band.
     */
    public function getUserPosition(User $user): ?string
    {
        $membership = $this->members()
            ->wherePivot('user_id', $user->id)
            ->first();

        return $membership?->pivot->position;
    }

    /**
     * Add a member to the band with optional role and position.
     */
    public function addMember(User $user, string $role = 'member', ?string $position = null): void
    {
        if (!$this->hasMember($user)) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'position' => $position,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Invite a user to join the band.
     */
    public function inviteMember(User $user, string $role = 'member', ?string $position = null): void
    {
        if (!$this->hasMember($user) && !$this->hasInvitedUser($user)) {
            $this->members()->attach($user->id, [
                'role' => $role,
                'position' => $position,
                'status' => 'invited',
                'invited_at' => now(),
            ]);
            
            // Send notification
            $user->notify(new \App\Notifications\BandInvitationNotification(
                $this,
                $role,
                $position
            ));
        }
    }

    /**
     * Check if a user has been invited to this band.
     */
    public function hasInvitedUser(User $user): bool
    {
        return $this->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('status', 'invited')
            ->exists();
    }

    /**
     * Accept an invitation to join the band.
     */
    public function acceptInvitation(User $user): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'status' => 'active',
        ]);
        
        // Notify band owner and admins about the new member
        $adminsAndOwner = $this->activeMembers()
            ->wherePivot('role', 'admin')
            ->get()
            ->push($this->owner)
            ->unique('id')
            ->filter(fn($u) => $u->id !== $user->id); // Don't notify the person who just joined
            
        foreach ($adminsAndOwner as $admin) {
            $admin->notify(new \App\Notifications\BandInvitationAcceptedNotification(
                $this,
                $user
            ));
        }
    }

    /**
     * Decline an invitation to join the band.
     */
    public function declineInvitation(User $user): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);
    }

    /**
     * Remove a member from the band.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    /**
     * Update a member's role in the band.
     */
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Update a member's position in the band.
     */
    public function updateMemberPosition(User $user, ?string $position): void
    {
        $this->members()->updateExistingPivot($user->id, ['position' => $position]);
    }
}
