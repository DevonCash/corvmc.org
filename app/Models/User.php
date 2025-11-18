<?php

namespace App\Models;

use App\Concerns\HasCredits;
use App\Concerns\HasMembershipStatus;
use App\Data\UserSettingsData;
use App\Enums\CreditType;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Cashier\Billable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property-read MemberProfile|null $profile
 * @property-read StaffProfile|null $staffProfile
 * @property-read Collection<int, Band> $bands
 * @property-read Collection<int, Band> $ownedBands
 * @property-read Collection<int, BandMember> $bandMemberships
 * @property-read Collection<int, RehearsalReservation> $rehearsals
 * @property-read Collection<int, Event> $productions
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, Reservation> $reservations
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasCredits, HasFactory, HasMembershipStatus, HasRoles, Impersonate, LogsActivity, Notifiable, SoftDeletes;

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
        // Staff panel requires staff or admin role
        if ($panel->getId() === 'staff') {
            return $this->hasRole(['admin', 'staff']);
        }

        // Member panel is accessible to all authenticated users
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

    public function productions(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    // Alias for backward compatibility
    public function events(): HasMany
    {
        return $this->productions();
    }

    public function bands(): BelongsToMany
    {
        return $this->belongsToMany(Band::class, 'band_profile_members', 'user_id', 'band_profile_id')
            ->withPivot('role', 'position', 'status')
            ->withTimestamps();
    }

    public function bandMemberships(): HasMany
    {
        return $this->hasMany(BandMember::class, 'user_id');
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function ownedBands(): HasMany
    {
        return $this->hasMany(Band::class, 'owner_id');
    }

    public function rehearsals(): MorphMany
    {
        return $this->morphMany(RehearsalReservation::class, 'reservable');
    }

    public function reservations(): MorphMany
    {
        return $this->morphMany(Reservation::class, 'reservable');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    /**
     * Check if user is a sustaining member (has monthly donation > $10).
     */
    public function isSustainingMember(): bool
    {
        return $this->hasRole('sustaining member');
    }

    /**
     * Get used free hours for the current month.
     *
     * @param  bool  $fresh  Deprecated, kept for compatibility
     */
    public function getUsedFreeHoursThisMonth(): float
    {
        if (!$this->isSustainingMember()) {
            return 0;
        }

        // Sum all negative credit transactions (deductions) this month
        $usedBlocks = \App\Models\CreditTransaction::where('user_id', $this->id)
            ->where('credit_type', CreditType::FreeHours)
            ->where('amount', '<', 0)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        // Convert negative value to positive and blocks to hours
        return Reservation::blocksToHours(abs($usedBlocks));
    }

    /**
     * Get remaining free hours for sustaining members this month.
     *
     * @param  bool  $fresh  If true, bypass cache for transaction-safe calculation
     */
    public function getRemainingFreeHours(): float
    {
        if (!$this->isSustainingMember()) {
            return 0;
        }

        // Use Credits System exclusively
        $balanceInBlocks = $this->getCreditBalance(CreditType::FreeHours);

        return Reservation::blocksToHours($balanceInBlocks);
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
        $this->notify(new EmailVerificationNotification);
    }

    public static function me(): ?self
    {
        /* @var self|null $user */
        $user = Auth::user();

        return $user;
    }
}
