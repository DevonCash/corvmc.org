<?php

namespace CorvMC\Events\Actions\Tickets;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDoorSale
{
    use AsAction;

    /**
     * Create a door sale order with immediate completion.
     *
     * Door sales bypass the checkout flow - payment is collected at the door
     * and the order is immediately marked as completed.
     *
     * @param  Event  $event  The event to sell tickets for
     * @param  int  $quantity  Number of tickets
     * @param  string  $paymentMethod  Payment method (cash, card)
     * @param  User  $staffUser  Staff member processing the sale
     * @param  int|null  $priceOverride  Override total price in cents (for special pricing)
     * @param  bool  $isSustainingMember  Whether buyer is a sustaining member (for discount)
     * @param  string|null  $attendeeName  Optional attendee name
     * @param  string|null  $attendeeEmail  Optional attendee email
     * @param  string|null  $notes  Optional notes about the sale
     * @return TicketOrder The completed order with generated tickets
     *
     * @throws \InvalidArgumentException If event doesn't have ticketing enabled
     * @throws \RuntimeException If tickets not available
     */
    public function handle(
        Event $event,
        int $quantity,
        string $paymentMethod,
        User $staffUser,
        ?int $priceOverride = null,
        bool $isSustainingMember = false,
        ?string $attendeeName = null,
        ?string $attendeeEmail = null,
        ?string $notes = null
    ): TicketOrder {
        // Validate ticketing is enabled
        if (! $event->hasNativeTicketing()) {
            throw new \InvalidArgumentException('Ticketing is not enabled for this event');
        }

        // Validate quantity
        $maxPerOrder = config('ticketing.max_tickets_per_order', 10);
        if ($quantity < 1 || $quantity > $maxPerOrder) {
            throw new \InvalidArgumentException("Quantity must be between 1 and {$maxPerOrder}");
        }

        // Validate availability
        if (! $event->hasTicketsAvailable($quantity)) {
            throw new \RuntimeException('Not enough tickets available');
        }

        return DB::transaction(function () use (
            $event,
            $quantity,
            $paymentMethod,
            $staffUser,
            $priceOverride,
            $isSustainingMember,
            $attendeeName,
            $attendeeEmail,
            $notes
        ) {
            // Calculate pricing
            $unitPrice = $this->calculateUnitPrice($event);
            $subtotal = $unitPrice->multipliedBy($quantity);
            $discount = $isSustainingMember
                ? $this->calculateMemberDiscount($event, $quantity)
                : Money::ofMinor(0, 'USD');

            // Use price override if provided, otherwise calculate
            $total = $priceOverride !== null
                ? Money::ofMinor($priceOverride, 'USD')
                : $subtotal->minus($discount);

            // Create the order (already completed)
            $order = TicketOrder::create([
                'user_id' => null, // Door sales don't require user account
                'event_id' => $event->id,
                'status' => TicketOrderStatus::Completed,
                'email' => $attendeeEmail,
                'name' => $attendeeName ?? 'Door Sale',
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => Money::ofMinor(0, 'USD'), // No processing fees for door sales
                'total' => $total,
                'covers_fees' => false,
                'is_door_sale' => true,
                'payment_method' => $paymentMethod,
                'completed_at' => now(),
                'notes' => $this->buildNotes($notes, $staffUser),
            ]);

            // Generate tickets immediately
            GenerateTickets::run($order);

            // Update event's tickets sold count
            $event->incrementTicketsSold($quantity);

            return $order;
        });
    }

    /**
     * Calculate unit price for the event.
     */
    private function calculateUnitPrice(Event $event): Money
    {
        $basePrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);

        return Money::ofMinor($basePrice, 'USD');
    }

    /**
     * Calculate the member discount amount.
     */
    private function calculateMemberDiscount(Event $event, int $quantity): Money
    {
        $basePrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);
        $discountPercent = config('ticketing.sustaining_member_discount', 50);
        $discountPerTicket = (int) round($basePrice * $discountPercent / 100);

        return Money::ofMinor($discountPerTicket * $quantity, 'USD');
    }

    /**
     * Build notes string including staff user info.
     */
    private function buildNotes(?string $notes, User $staffUser): string
    {
        $staffNote = "Processed by {$staffUser->name} (#{$staffUser->id})";

        return $notes ? "{$notes} | {$staffNote}" : $staffNote;
    }
}
