<?php

namespace CorvMC\Events\Models;

use App\Models\User;
use CorvMC\Events\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ticket - Individual ticket issued per attendee within a ticket order.
 *
 * Each ticket has a unique code for QR/barcode scanning at check-in.
 * Attendee fields are nullable to support anonymous door sales.
 *
 * @property int $id
 * @property int $ticket_order_id
 * @property string $code
 * @property string|null $attendee_name
 * @property string|null $attendee_email
 * @property TicketStatus $status
 * @property \Illuminate\Support\Carbon|null $checked_in_at
 * @property int|null $checked_in_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read TicketOrder $order
 * @property-read User|null $checkedInByUser
 * @property-read Event|null $event
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket valid()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket checkedIn()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ticket forEvent($eventId)
 *
 * @mixin \Eloquent
 */
class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_order_id',
        'code',
        'attendee_name',
        'attendee_email',
        'status',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->code)) {
                $ticket->code = self::generateUniqueCode();
            }
        });
    }

    /**
     * Generate a unique ticket code.
     * Format: 8 uppercase alphanumeric characters (no ambiguous chars).
     */
    public static function generateUniqueCode(): string
    {
        // Exclude ambiguous characters: 0, O, I, L, 1
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Get the event for this ticket (through order).
     */
    public function getEventAttribute(): ?Event
    {
        return $this->order?->event;
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeValid($query)
    {
        return $query->where('status', TicketStatus::Valid);
    }

    public function scopeCheckedIn($query)
    {
        return $query->where('status', TicketStatus::CheckedIn);
    }

    public function scopeForEvent($query, $eventId)
    {
        return $query->whereHas('order', function ($q) use ($eventId) {
            $q->where('event_id', $eventId);
        });
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if this ticket can be used for entry.
     */
    public function canCheckIn(): bool
    {
        return $this->status->canCheckIn();
    }

    /**
     * Check in this ticket.
     */
    public function checkIn(?User $checkedInBy = null): self
    {
        if (!$this->canCheckIn()) {
            throw new \RuntimeException('Ticket cannot be checked in: status is '.$this->status->label());
        }

        $this->update([
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
            'checked_in_by' => $checkedInBy?->id,
        ]);

        return $this;
    }

    /**
     * Cancel this ticket.
     */
    public function cancel(): self
    {
        $this->update([
            'status' => TicketStatus::Cancelled,
        ]);

        return $this;
    }

    /**
     * Get the display name for this ticket holder.
     */
    public function getHolderName(): string
    {
        return $this->attendee_name ?? $this->order?->getPurchaserName() ?? 'Guest';
    }

    /**
     * Get the email for this ticket holder.
     */
    public function getHolderEmail(): ?string
    {
        return $this->attendee_email ?? $this->order?->getPurchaserEmail();
    }

    /**
     * Get the URL-safe identifier for this ticket.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
