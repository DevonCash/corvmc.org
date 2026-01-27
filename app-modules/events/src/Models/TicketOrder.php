<?php

namespace CorvMC\Events\Models;

use App\Models\User;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Finance\Concerns\HasCharges;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Support\Casts\MoneyCast;
use Database\Factories\TicketOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TicketOrder - A purchase transaction for event tickets.
 *
 * Implements Chargeable interface for Finance module integration.
 * Supports both authenticated users and guest checkout (user_id nullable).
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property int $event_id
 * @property TicketOrderStatus $status
 * @property string|null $email
 * @property string|null $name
 * @property int $quantity
 * @property \Brick\Money\Money $unit_price
 * @property \Brick\Money\Money $subtotal
 * @property \Brick\Money\Money $discount
 * @property \Brick\Money\Money $fees
 * @property \Brick\Money\Money $total
 * @property bool $covers_fees
 * @property bool $is_door_sale
 * @property string|null $payment_method
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder completed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketOrder forEvent($eventId)
 *
 * @mixin \Eloquent
 */
class TicketOrder extends Model implements Chargeable
{
    use HasCharges, HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'event_id',
        'status',
        'email',
        'name',
        'quantity',
        'unit_price',
        'subtotal',
        'discount',
        'fees',
        'total',
        'covers_fees',
        'is_door_sale',
        'payment_method',
        'completed_at',
        'refunded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketOrderStatus::class,
            'unit_price' => MoneyCast::class.':USD',
            'subtotal' => MoneyCast::class.':USD',
            'discount' => MoneyCast::class.':USD',
            'fees' => MoneyCast::class.':USD',
            'total' => MoneyCast::class.':USD',
            'covers_fees' => 'boolean',
            'is_door_sale' => 'boolean',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TicketOrderFactory
    {
        return TicketOrderFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (TicketOrder $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    // =========================================================================
    // Chargeable Interface Implementation
    // =========================================================================

    public function getBillableUnits(): float
    {
        return 1; // One order = one charge
    }

    public function getChargeableDescription(): string
    {
        $eventTitle = $this->event?->title ?? 'Event';
        $date = $this->event?->start_datetime?->format('M j, Y') ?? 'TBD';

        return "{$this->quantity} ticket(s) for {$eventTitle} on {$date}";
    }

    public function getBillableUser(): User
    {
        // For guest checkout, we create a temporary billing context
        // The webhook handler will use the email from the order
        if ($this->user) {
            return $this->user;
        }

        // This shouldn't happen for Stripe checkout as we always have email
        throw new \RuntimeException('No billable user found for ticket order');
    }

    /**
     * Get the total amount to charge (in cents).
     * Used by the checkout process.
     */
    public function getChargeableAmount(): int
    {
        return $this->total->getMinorAmount()->toInt();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopePending($query)
    {
        return $query->where('status', TicketOrderStatus::Pending);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', TicketOrderStatus::Completed);
    }

    public function scopeForEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if this order can be refunded.
     */
    public function canRefund(): bool
    {
        return $this->status->canRefund();
    }

    /**
     * Check if this order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === TicketOrderStatus::Completed;
    }

    /**
     * Get the purchaser's display name.
     */
    public function getPurchaserName(): string
    {
        return $this->name ?? $this->user?->name ?? 'Guest';
    }

    /**
     * Get the purchaser's email address.
     */
    public function getPurchaserEmail(): ?string
    {
        return $this->email ?? $this->user?->email;
    }

    /**
     * Mark this order as completed.
     */
    public function markAsCompleted(?string $paymentMethod = null): self
    {
        $this->update([
            'status' => TicketOrderStatus::Completed,
            'completed_at' => now(),
            'payment_method' => $paymentMethod ?? $this->payment_method,
        ]);

        return $this;
    }

    /**
     * Mark this order as refunded.
     */
    public function markAsRefunded(?string $notes = null): self
    {
        $this->update([
            'status' => TicketOrderStatus::Refunded,
            'refunded_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        return $this;
    }

    /**
     * Mark this order as cancelled.
     */
    public function markAsCancelled(?string $notes = null): self
    {
        $this->update([
            'status' => TicketOrderStatus::Cancelled,
            'notes' => $notes ?? $this->notes,
        ]);

        return $this;
    }

    /**
     * Get the URL-safe identifier for this order.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
