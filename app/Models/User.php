<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Concerns\HasMembershipStatus;
use App\Data\UserSettingsData;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\PasswordResetNotification;
use App\Notifications\EmailVerificationNotification;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Cashier\Billable;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, Impersonate, Billable, SoftDeletes, HasMembershipStatus;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'pronouns',
        'email',
        'email_verified_at',
        'password',
        'trust_points',
        'community_event_trust_points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'settings',
    ];

    public function canAccessPanel($panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        // Use the profile's media library avatar with thumbnail conversion
        if ($this->profile && $this->profile->hasMedia('avatar')) {
            return $this->profile->getFirstMediaUrl('avatar', 'thumb');
        }

        // Fall back to the full avatar URL if no thumbnail conversion exists
        return $this->profile?->avatar_url;
    }

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
            'settings' => UserSettingsData::class,
            'staff_social_links' => 'array',
            'trust_points' => 'array',
        ];
    }

    /**
     * Get the user's profile, creating one if it doesn't exist.
     */
    public function getProfileAttribute()
    {
        // If relationship is already loaded, return it
        if ($this->relationLoaded('profile')) {
            return $this->getRelation('profile');
        }

        // Load or create the profile with error handling for race conditions
        try {
            $profile = $this->profile()->firstOrCreate(['user_id' => $this->id]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate key violation (race condition)
            if ($e->getCode() === '23505') {
                $profile = $this->profile()->first();
            } else {
                throw $e;
            }
        }

        // Set the relationship so subsequent calls use the loaded instance
        $this->setRelation('profile', $profile);

        return $profile;
    }

    public function productions()
    {
        return $this->hasMany(Production::class, 'manager_id');
    }

    // @deprecated use bands() instead
    public function bandProfiles()
    {
        return $this->belongsToMany(Band::class, 'band_profile_members', 'user_id', 'band_profile_id')
            ->withPivot('role', 'position')
            ->withTimestamps();
    }

    public function bands()
    {
        return $this->belongsToMany(Band::class, 'band_profile_members', 'user_id', 'band_profile_id')
            ->withPivot('role', 'position', 'status')
            ->withTimestamps();
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function ownedBands()
    {
        return $this->hasMany(Band::class, 'owner_id');
    }

    public function rehearsals()
    {
        return $this->morphMany(RehearsalReservation::class, 'reservable');
    }

    /**
     * Alias for rehearsals() for backward compatibility.
     */
    public function reservations()
    {
        return $this->rehearsals();
    }

    public function profile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    /**
     * Check if user is a sustaining member (has monthly donation > $10).
     */
    public function isSustainingMember(): bool
    {
        return \App\Actions\MemberBenefits\CheckIsSustainingMember::run($this);
    }

    /**
     * Get used free hours for the current month.
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation
     */
    public function getUsedFreeHoursThisMonth(bool $fresh = false): float
    {
        // Note: This uses legacy calculation via GetRemainingFreeHours action
        $action = new \App\Actions\MemberBenefits\GetRemainingFreeHours();
        return $action->getUsedFreeHoursThisMonth($this, $fresh);
    }

    /**
     * Get remaining free hours for sustaining members this month.
     *
     * @param bool $fresh If true, bypass cache for transaction-safe calculation
     */
    public function getRemainingFreeHours(bool $fresh = false): float
    {
        return \App\Actions\MemberBenefits\GetRemainingFreeHours::run($this, $fresh);
    }

    public function scopeStaffMembers($query)
    {
        return $query->where('show_on_about_page', true)
            ->whereNotNull('staff_type');
    }

    public function scopeBoardMembers($query)
    {
        return $query->staffMembers()->where('staff_type', 'board');
    }

    public function scopeStaffOnly($query)
    {
        return $query->staffMembers()->where('staff_type', 'staff');
    }

    public function scopeStaffOrdered($query)
    {
        return $query->orderBy('staff_sort_order')->orderBy('name');
    }

    public function getStaffProfileImageUrlAttribute(): ?string
    {
        return $this->getFilamentAvatarUrl();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'staff_title', 'staff_type', 'show_on_about_page'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "User account {$eventName}");
    }


    public function sendPasswordResetNotification($token)
    {
        $this->notify(new PasswordResetNotification($token));
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new EmailVerificationNotification());
    }
}
