<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

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
    use HasFactory, HasRoles, LogsActivity, Notifiable, Impersonate, Billable, SoftDeletes;

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

        // Load or create the profile
        $profile = $this->profile()->first();

        if (!$profile) {
            $profile = $this->profile()->create(['user_id' => $this->id]);
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

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'email', 'email');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function profile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    public static function me(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    /**
     * Check if user is a sustaining member (has monthly donation > $10).
     */
    public function isSustainingMember(): bool
    {
        return \App\Facades\UserSubscriptionService::isSustainingMember($this);
    }

    /**
     * Get used free hours for the current month.
     */
    public function getUsedFreeHoursThisMonth(): float
    {
        return \App\Facades\UserSubscriptionService::getUsedFreeHoursThisMonth($this);
    }

    /**
     * Get remaining free hours for sustaining members this month.
     */
    public function getRemainingFreeHours(): float
    {
        return \App\Facades\UserSubscriptionService::getRemainingFreeHours($this);
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
