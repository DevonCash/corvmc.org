<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents input from the Zeffy api Zapier integration.
 * Used to track donations and purchases, associated with users via email,
 * and potentially linked to other models in the application.
 *
 * The content field should store the response from Zeffy.
 */
class Transaction extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id',
        'email',
        'amount',
        'currency',
        'type',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
