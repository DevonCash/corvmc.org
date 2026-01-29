<?php

namespace CorvMC\Kiosk\Models;

use CorvMC\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_device_id
 * @property int $target_device_id
 * @property int $event_id
 * @property int $amount
 * @property int $quantity
 * @property string|null $customer_email
 * @property bool $is_sustaining_member
 * @property string $status
 * @property string|null $payment_intent_id
 * @property string|null $failure_reason
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read KioskDevice $sourceDevice
 * @property-read KioskDevice $targetDevice
 * @property-read Event $event
 *
 * @method static Builder<static>|KioskPaymentRequest pending()
 * @method static Builder<static>|KioskPaymentRequest forTarget(KioskDevice $device)
 * @method static Builder<static>|KioskPaymentRequest notExpired()
 */
class KioskPaymentRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COLLECTING = 'collecting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'source_device_id',
        'target_device_id',
        'event_id',
        'amount',
        'quantity',
        'customer_email',
        'is_sustaining_member',
        'status',
        'payment_intent_id',
        'failure_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'quantity' => 'integer',
            'is_sustaining_member' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * The device that initiated this payment request.
     */
    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class, 'source_device_id');
    }

    /**
     * The tap-to-pay device that will collect this payment.
     */
    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class, 'target_device_id');
    }

    /**
     * The event this payment is for.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForTarget(Builder $query, KioskDevice $device): Builder
    {
        return $query->where('target_device_id', $device->id);
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    // =========================================================================
    // Status Methods
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCollecting(): bool
    {
        return $this->status === self::STATUS_COLLECTING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Mark this request as being collected (tap-to-pay device is processing).
     */
    public function markAsCollecting(): void
    {
        $this->update(['status' => self::STATUS_COLLECTING]);
    }

    /**
     * Mark this request as completed.
     */
    public function markAsCompleted(string $paymentIntentId): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    /**
     * Mark this request as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark this request as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }
}
