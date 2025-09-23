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
 * @property int $community_event_trust_points
 * @property array<array-key, mixed>|null $trust_points
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData $settings
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Band> $bandProfiles
 * @property-read int|null $band_profiles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Band> $bands
 * @property-read int|null $bands_count
 * @property-read \App\Models\MemberProfile|null $profile
 * @property-read string|null $staff_profile_image_url
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Band> $ownedBands
 * @property-read int|null $owned_bands_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production> $productions
 * @property-read int|null $productions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservation> $reservations
 * @property-read int|null $reservations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \App\Models\StaffProfile|null $staffProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Cashier\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCommunityEventTrustPoints($value)
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTrustPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @mixin \Eloquent
 */
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
        return \App\Facades\MemberBenefitsService::isSustainingMember($this);
    }

    /**
     * Get used free hours for the current month.
     */
    public function getUsedFreeHoursThisMonth(): float
    {
        return \App\Facades\MemberBenefitsService::getUsedFreeHoursThisMonth($this);
    }

    /**
     * Get remaining free hours for sustaining members this month.
     */
    public function getRemainingFreeHours(): float
    {
        return \App\Facades\MemberBenefitsService::getRemainingFreeHours($this);
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
