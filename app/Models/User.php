<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Data\UserSettingsData;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
        'settings'
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
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            $user->profile()->create([
                'user_id' => $user->id,
            ]);
        });
    }

    public function productions()
    {
        return $this->hasMany(Production::class, 'manager_id');
    }

    public function bandProfiles()
    {
        return $this->belongsToMany(BandProfile::class, 'band_profile_members')
            ->withPivot('role', 'position')
            ->withTimestamps();
    }

    public function ownedBands()
    {
        return $this->hasMany(BandProfile::class, 'owner_id');
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
        return $this->hasRole('sustaining member') || 
               $this->transactions()
                   ->where('type', 'recurring')
                   ->where('amount', '>', 10)
                   ->where('created_at', '>=', now()->subMonth())
                   ->exists();
    }

    /**
     * Get used free hours for the current month.
     */
    public function getUsedFreeHoursThisMonth(): float
    {
        return $this->reservations()
            ->whereMonth('reserved_at', now()->month)
            ->whereYear('reserved_at', now()->year)
            ->sum('free_hours_used') ?? 0;
    }

    /**
     * Get remaining free hours for sustaining members this month.
     */
    public function getRemainingFreeHours(): float
    {
        if (!$this->isSustainingMember()) {
            return 0;
        }
        
        return max(0, 4 - $this->getUsedFreeHoursThisMonth());
    }

}
