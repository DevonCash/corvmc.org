<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a reservation at the practice space.
 * It includes details about the user who made the reservation,
 * a production associated with the reservation (if applicable),
 * and the status of the reservation.
 */

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'production_id',
        'status',
        'reserved_at',
        'reserved_until'
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'reserved_until' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

}
