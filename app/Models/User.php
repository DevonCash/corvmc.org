<?php

namespace App\Models;

use App\Concerns\HasCredits;
use CorvMC\Membership\Concerns\HasMembershipStatus;
use CorvMC\Sponsorship\Models\Sponsor;
use CorvMC\Membership\Data\UserSettingsData;
use App\Enums\CreditType;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use CorvMC\Moderation\Concerns\HasTrust;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Cashier\Billable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $pronouns
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property string|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData $settings
 * @property-read Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Collection<int, \App\Models\BandMember> $bandMemberships
 * @property-read int|null $band_memberships_count
 * @property-read Collection<int, \App\Models\Band> $bands
 * @property-read int|null $bands_count
 * @property-read Collection<int, \App\Models\CreditTransaction> $creditTransactions
 * @property-read int|null $credit_transactions_count
 * @property-read Collection<int, \App\Models\UserCredit> $credits
 * @property-read int|null $credits_count
 * @property-read Collection<int, \CorvMC\Events\Models\Event> $events
 * @property-read int|null $events_count
 * @property-read \App\Models\MemberProfile|null $profile
 * @property-read string|null $staff_profile_image_url
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, \App\Models\Band> $ownedBands
 * @property-read int|null $owned_bands_count
 * @property-read Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, \CorvMC\Events\Models\Event> $productions
 * @property-read int|null $productions_count
 * @property-read Collection<int, \App\Models\RehearsalReservation> $rehearsals
 * @property-read int|null $rehearsals_count
 * @property-read Collection<int, \App\Models\Reservation> $reservations
 * @property-read int|null $reservations_count
 * @property-read Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, \CorvMC\Sponsorship\Models\Sponsor> $sponsors
 * @property-read int|null $sponsors_count
 * @property-read \App\Models\StaffProfile|null $staffProfile
 * @property-read Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read Collection<int, \App\Models\TrustAchievement> $trustAchievements
 * @property-read int|null $trust_achievements_count
 * @property-read Collection<int, \App\Models\UserTrustBalance> $trustBalances
 * @property-read int|null $trust_balances_count
 * @property-read Collection<int, \App\Models\TrustTransaction> $trustTransactions
 * @property-read int|null $trust_transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User boardMembers()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User hasExpiredGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onGenericTrial()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User staffMembers()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User staffOnly()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User staffOrdered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmLastFour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePmType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePronouns($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStripeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements FilamentUser, HasAvatar, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasCredits, HasFactory, HasMembershipStatus, HasRoles, HasTrust, Impersonate, LogsActivity, Notifiable, SoftDeletes;

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

    public function getTenants(Panel $panel): Collection
    {
        // Only provide tenants for band panel
        if ($panel->getId() !== 'band') {
            return new Collection();
        }

        // Return all bands where user is active member or owner
        return $this->bands()
            ->wherePivot('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function canAccessTenant(\Illuminate\Database\Eloquent\Model $tenant): bool
    {
        // Use existing BandPolicy logic
        return $tenant instanceof Band
            && ($tenant->owner_id === $this->id
                || $tenant->activeMembers()->where('user_id', $this->id)->exists());
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

    public function sponsors(): BelongsToMany
    {
        return $this->belongsToMany(Sponsor::class, 'sponsor_user')
            ->withTimestamps()
            ->orderBy('sponsor_user.created_at', 'desc');
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
     */
    public function getUsedFreeHoursThisMonth(): float
    {
        if (! $this->isSustainingMember()) {
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
        if (! $this->isSustainingMember()) {
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
            ->setDescriptionForEvent(fn (string $eventName) => "User account {$eventName}");
    }

    // public function sendPasswordResetNotification($token)
    // {
    //     $this->notify(new PasswordResetNotification($token));
    // }
    
    // Temporarily using stock Filament notification to debug signature issue

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
