<?php

namespace CorvMC\Events\Actions\Tickets;

use App\Models\User;
use Brick\Money\Money;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Actions\Payments\CalculateProcessingFee;
use CorvMC\Finance\Models\Charge;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTicketOrder
{
    use AsAction;

    /**
     * Create a ticket order with calculated pricing.
     *
     * @param  Event  $event  The event to purchase tickets for
     * @param  int  $quantity  Number of tickets
     * @param  string|null  $name  Purchaser name (for guests)
     * @param  string|null  $email  Purchaser email (for guests)
     * @param  User|null  $user  Authenticated user (if logged in)
     * @param  bool  $coversFees  Whether purchaser covers processing fees
     * @return TicketOrder The created order
     *
     * @throws \InvalidArgumentException If event doesn't have ticketing enabled
     * @throws \RuntimeException If tickets not available
     */
    public function handle(
        Event $event,
        int $quantity,
        ?string $name = null,
        ?string $email = null,
        ?User $user = null,
        bool $coversFees = false
    ): TicketOrder {
        // Validate ticketing is enabled
        if (!$event->hasNativeTicketing()) {
            throw new \InvalidArgumentException('Ticketing is not enabled for this event');
        }

        // Validate quantity
        $maxPerOrder = config('ticketing.max_tickets_per_order', 10);
        if ($quantity < 1 || $quantity > $maxPerOrder) {
            throw new \InvalidArgumentException("Quantity must be between 1 and {$maxPerOrder}");
        }

        // Validate availability
        if (!$event->hasTicketsAvailable($quantity)) {
            throw new \RuntimeException('Not enough tickets available');
        }

        // Require either user or guest info
        if (!$user && !$email) {
            throw new \InvalidArgumentException('Either user or email is required');
        }

        return DB::transaction(function () use ($event, $quantity, $name, $email, $user, $coversFees) {
            // Calculate pricing
            $unitPrice = $this->calculateUnitPrice($event, $user);
            $subtotal = $unitPrice->multipliedBy($quantity);
            $discount = $this->calculateDiscount($event, $user, $quantity);
            $fees = $coversFees ? CalculateProcessingFee::run($subtotal->minus($discount)) : Money::ofMinor(0, 'USD');
            $total = $subtotal->minus($discount)->plus($fees);

            // Create the order
            $order = TicketOrder::create([
                'user_id' => $user?->id,
                'event_id' => $event->id,
                'status' => TicketOrderStatus::Pending,
                'email' => $email ?? $user?->email,
                'name' => $name ?? $user?->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => $fees,
                'total' => $total,
                'covers_fees' => $coversFees,
            ]);

            // Create associated Charge record for Finance integration
            if ($user) {
                Charge::createForChargeable(
                    $order,
                    $subtotal->getMinorAmount()->toInt(),
                    $total->getMinorAmount()->toInt()
                );
            }

            return $order;
        });
    }

    /**
     * Calculate unit price for a user.
     */
    private function calculateUnitPrice(Event $event, ?User $user): Money
    {
        $basePrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);

        // Apply sustaining member discount
        if ($user && $user->isSustainingMember()) {
            $discountPercent = config('ticketing.sustaining_member_discount', 50);
            $basePrice = (int) round($basePrice * (1 - $discountPercent / 100));
        }

        return Money::ofMinor($basePrice, 'USD');
    }

    /**
     * Calculate the total discount amount.
     */
    private function calculateDiscount(Event $event, ?User $user, int $quantity): Money
    {
        if (!$user || !$user->isSustainingMember()) {
            return Money::ofMinor(0, 'USD');
        }

        $basePrice = $event->ticket_price_override ?? config('ticketing.default_price', 1000);
        $discountPercent = config('ticketing.sustaining_member_discount', 50);
        $discountPerTicket = (int) round($basePrice * $discountPercent / 100);

        return Money::ofMinor($discountPerTicket * $quantity, 'USD');
    }
}
