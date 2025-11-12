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
    public function for($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
