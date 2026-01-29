<?php

namespace CorvMC\Kiosk\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $api_key
 * @property bool $is_active
 * @property bool $has_tap_to_pay
 * @property int|null $payment_device_id
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read KioskDevice|null $paymentDevice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, KioskPaymentRequest> $pendingPaymentRequests
 *
 * @method static Builder<static>|KioskDevice active()
 * @method static Builder<static>|KioskDevice withTapToPay()
 */
class KioskDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_key',
        'is_active',
        'has_tap_to_pay',
        'payment_device_id',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_tap_to_pay' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KioskDevice $device) {
            if (empty($device->api_key)) {
                $device->api_key = self::generateApiKey();
            }
        });
    }

    /**
     * Generate a secure, unique API key.
     */
    public static function generateApiKey(): string
    {
        do {
            $key = Str::random(64);
        } while (self::where('api_key', $key)->exists());

        return $key;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * The default tap-to-pay device for this kiosk (for pushed payments).
     */
    public function paymentDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class, 'payment_device_id');
    }

    /**
     * Payment requests targeting this device (as the tap-to-pay receiver).
     */
    public function pendingPaymentRequests(): HasMany
    {
        return $this->hasMany(KioskPaymentRequest::class, 'target_device_id')
            ->where('status', 'pending');
    }

    /**
     * All payment requests this device has initiated.
     */
    public function initiatedPaymentRequests(): HasMany
    {
        return $this->hasMany(KioskPaymentRequest::class, 'source_device_id');
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeWithTapToPay(Builder $query): Builder
    {
        return $query->where('has_tap_to_pay', true);
    }

    // =========================================================================
    // Capability Methods
    // =========================================================================

    /**
     * Check if this device can do the door workflow (direct tap-to-pay collection).
     */
    public function canDoDoorWorkflow(): bool
    {
        return $this->has_tap_to_pay;
    }

    /**
     * Check if this device can push payments to a linked tap-to-pay device.
     */
    public function canPushPayments(): bool
    {
        return $this->payment_device_id !== null;
    }

    /**
     * Check if this device can accept card payments (either directly or via push).
     */
    public function canAcceptCardPayments(): bool
    {
        return $this->canDoDoorWorkflow() || $this->canPushPayments();
    }

    /**
     * Update the last seen timestamp.
     */
    public function touch($attribute = null): bool
    {
        if ($attribute === null) {
            $this->last_seen_at = now();
        }

        return parent::touch($attribute);
    }

    /**
     * Mark this device as seen (update last_seen_at).
     */
    public function markAsSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
