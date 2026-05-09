<?php

namespace CorvMC\Events\Services;

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\TransactionState\Pending;
use CorvMC\Support\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for managing event tickets and ticket orders.
 *
 * Ticket purchases flow through the Finance Order system:
 * createOrder → Finance::price/commit → Stripe checkout → webhook settles → tickets generated.
 */
class TicketService
{
    /**
     * Create a ticket order for an authenticated user.
     *
     * @return TicketOrder The created ticket order
     *
     * @throws \RuntimeException if not enough tickets available
     */
    public function createOrder(Event $event, User $purchaser, int $quantity, bool $coversFees = false): TicketOrder
    {
        $this->guardAvailability($event, $quantity);

        $unitPriceCents = $event->getTicketPriceForUser($purchaser)->getMinorAmount();

        return $this->buildTicketOrder($event, $quantity, $unitPriceCents, $coversFees, [
            'user_id' => $purchaser->id,
            'name' => $purchaser->name,
            'email' => $purchaser->email,
        ]);
    }

    /**
     * Create a ticket order for a guest (no authenticated user).
     *
     * @return TicketOrder The created ticket order
     *
     * @throws \RuntimeException if not enough tickets available
     */
    public function guestCheckout(Event $event, int $quantity, string $name, string $email, bool $coversFees = false): TicketOrder
    {
        $this->guardAvailability($event, $quantity);

        $unitPriceCents = $event->getTicketPriceForUser(null)->getMinorAmount();

        return $this->buildTicketOrder($event, $quantity, $unitPriceCents, $coversFees, [
            'user_id' => null,
            'name' => $name,
            'email' => $email,
        ]);
    }

    /**
     * Full checkout flow: create a ticket order and process payment.
     *
     * For authenticated users, pass a User. For guests, pass name/email instead.
     * Returns a redirect URL — either a Stripe checkout page or the success URL (for free tickets).
     *
     * @throws \RuntimeException if not enough tickets available
     */
    public function checkout(
        Event $event,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?User $user = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
        bool $coversFees = false,
    ): string {
        $ticketOrder = $user
            ? $this->createOrder($event, $user, $quantity, $coversFees)
            : $this->guestCheckout($event, $quantity, $guestName, $guestEmail, $coversFees);

        return $this->processCheckout($ticketOrder, $successUrl, $cancelUrl);
    }

    /**
     * Process checkout for a ticket order via the Finance Order system.
     *
     * Creates a Finance Order, prices it, commits with Stripe rail,
     * and returns the redirect URL.
     */
    public function processCheckout(TicketOrder $ticketOrder, string $successUrl, string $cancelUrl): string
    {
        // Guard against duplicate orders
        $existingOrder = Finance::findActiveOrder($ticketOrder);
        if ($existingOrder) {
            return $existingOrder->checkoutUrl();
        }

        $user = $ticketOrder->user;

        // Price via Finance
        $lineItems = Finance::price([$ticketOrder], $user);
        $totalCents = (int) $lineItems->sum('amount');

        if ($totalCents <= 0) {
            // Free tickets — create Order, commit settles immediately.
            // The GenerateTicketsOnOrderSettled listener handles ticket
            // generation and marking the TicketOrder completed.
            $order = $this->createFinanceOrder($ticketOrder, $lineItems, $totalCents);
            Finance::commit($order->fresh(), []);

            return $successUrl;
        }

        // Create Finance Order and commit with Stripe
        $order = $this->createFinanceOrder($ticketOrder, $lineItems, $totalCents);
        $committed = Finance::commit($order->fresh(), ['stripe' => $totalCents], checkoutUrls: [
            'success_url' => $successUrl.'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ], customerEmail: $ticketOrder->email);

        return $committed->checkoutUrl();
    }

    /**
     * Complete a ticket order after successful payment.
     * Called by the webhook handler after Finance::settle() succeeds.
     */
    public function completeOrder(int $ticketOrderId, ?string $sessionId = null): bool
    {
        $ticketOrder = TicketOrder::find($ticketOrderId);

        if (! $ticketOrder) {
            return false;
        }

        // If called from webhook with session ID, settle the Finance Transaction
        if ($sessionId) {
            $financeOrder = Finance::findActiveOrder($ticketOrder);
            if ($financeOrder) {
                $stripeTxn = $financeOrder->transactions()
                    ->where('currency', 'stripe')
                    ->where('type', 'payment')
                    ->whereState('status', Pending::class)
                    ->first();

                if ($stripeTxn) {
                    try {
                        Finance::settle($stripeTxn);
                    } catch (\RuntimeException $e) {
                        // Already settled — that's fine
                    }
                }
            }
        }

        $this->completeTicketOrder($ticketOrder);

        return true;
    }

    /**
     * Create a door sale (in-person ticket sale).
     * Creates a Finance Order with payment method, settled immediately.
     */
    public function createDoorSale(
        Event $event,
        int $quantity,
        string $paymentMethod = 'cash',
        ?User $staffUser = null,
        ?float $priceOverride = null,
        ?bool $isSustainingMember = false,
        ?string $attendeeName = null,
        ?string $attendeeEmail = null,
        ?string $notes = null
    ): TicketOrder {
        if (! $staffUser) {
            throw new \Exception('staffUser is required for door sales');
        }

        return DB::transaction(function () use ($event, $quantity, $paymentMethod, $staffUser, $priceOverride, $isSustainingMember, $attendeeName, $attendeeEmail, $notes) {
            // Get base unit price in cents
            $basePrice = $event->getBaseTicketPrice()->getMinorAmount();
            $unitPriceCents = (int) ($priceOverride ?? $basePrice);
            $subtotalCents = $unitPriceCents * $quantity;

            // Calculate discount if sustaining member
            $discountCents = 0;
            if ($isSustainingMember) {
                $discountPercent = config('ticketing.sustaining_member_discount', 50);
                $discountCents = (int) round($subtotalCents * $discountPercent / 100);
            }

            $totalCents = max($subtotalCents - $discountCents, 0);

            $ticketOrder = TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => $staffUser->id,
                'quantity' => $quantity,
                'unit_price' => Money::ofMinor($unitPriceCents, 'USD'),
                'subtotal' => Money::ofMinor($subtotalCents, 'USD'),
                'discount' => Money::ofMinor($discountCents, 'USD'),
                'fees' => Money::ofMinor(0, 'USD'),
                'total' => Money::ofMinor($totalCents, 'USD'),
                'status' => 'completed',
                'completed_at' => now(),
                'is_door_sale' => true,
                'payment_method' => $paymentMethod,
                'name' => $attendeeName,
                'email' => $attendeeEmail,
                'notes' => $notes ?? "Door sale by {$staffUser->name}",
            ]);

            // Generate tickets immediately
            $this->generateTickets($ticketOrder);

            // Create Finance Order with payment method, settled immediately
            if ($totalCents > 0) {
                $lineItems = Finance::price([$ticketOrder], $staffUser);
                $netCents = (int) $lineItems->sum('amount');
                $order = $this->createFinanceOrder($ticketOrder, $lineItems, $netCents);
                $committed = Finance::commit($order->fresh(), [$paymentMethod => $netCents]);

                // Settle the transaction immediately
                $txn = $committed->transactions->first();
                if ($txn) {
                    Finance::settle($txn);
                }
            }

            return $ticketOrder;
        });
    }

    /**
     * Refund a ticket order via Finance::refund().
     */
    public function refundOrder(TicketOrder $ticketOrder, ?string $reason = null): TicketOrder
    {
        return DB::transaction(function () use ($ticketOrder, $reason) {
            // Refund via Finance Order
            $financeOrder = Finance::findActiveOrder($ticketOrder);
            if ($financeOrder) {
                Finance::refund($financeOrder);
            }

            $ticketOrder->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            // Void all tickets
            $ticketOrder->tickets()->update(['status' => 'voided']);

            return $ticketOrder->fresh();
        });
    }

    /**
     * Cancel a pending ticket order.
     */
    public function cancelOrder(TicketOrder $ticketOrder, ?string $reason = null): TicketOrder
    {
        if ($ticketOrder->status !== 'pending') {
            throw new \Exception('Only pending orders can be cancelled');
        }

        // Cancel Finance Order
        $financeOrder = Finance::findActiveOrder($ticketOrder);
        if ($financeOrder) {
            Finance::cancel($financeOrder);
        }

        $ticketOrder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $ticketOrder->fresh();
    }

    /**
     * Generate tickets for a completed order.
     */
    public function generateTickets(TicketOrder $order): Collection
    {
        $tickets = collect();

        for ($i = 0; $i < $order->quantity; $i++) {
            $ticket = Ticket::create([
                'ticket_order_id' => $order->id,
                'event_id' => $order->event_id,
                'ticket_number' => $this->generateTicketNumber(),
                'status' => 'valid',
            ]);
            $tickets->push($ticket);
        }

        return $tickets;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create a Finance Order with LineItems for a TicketOrder.
     */
    private function createFinanceOrder(TicketOrder $ticketOrder, Collection $lineItems, int $totalCents): Order
    {
        $order = Order::create([
            'user_id' => $ticketOrder->user_id,
            'total_amount' => 0,
        ]);

        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }

        $order->update(['total_amount' => $totalCents]);

        return $order;
    }

    /**
     * Mark a TicketOrder as completed and generate tickets.
     */
    private function completeTicketOrder(TicketOrder $ticketOrder): void
    {
        $ticketOrder->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->generateTickets($ticketOrder);
    }

    /**
     * Guard that enough tickets are available.
     *
     * @throws \RuntimeException
     */
    private function guardAvailability(Event $event, int $quantity): void
    {
        $remaining = $event->getTicketsRemaining();

        if ($remaining !== null && $quantity > $remaining) {
            throw new \RuntimeException("Only {$remaining} tickets available for this event");
        }
    }

    /**
     * Build a TicketOrder with correct pricing fields.
     *
     * @param  array<string, mixed>  $attributes  Additional attributes (user_id, name, email)
     */
    private function buildTicketOrder(Event $event, int $quantity, int $unitPriceCents, bool $coversFees, array $attributes): TicketOrder
    {
        return DB::transaction(function () use ($event, $quantity, $unitPriceCents, $coversFees, $attributes) {
            $subtotalCents = $unitPriceCents * $quantity;
            $feesCents = $coversFees
                ? (int) round($subtotalCents * 0.029) + 30
                : 0;
            $totalCents = $subtotalCents + $feesCents;

            return TicketOrder::create(array_merge($attributes, [
                'event_id' => $event->id,
                'quantity' => $quantity,
                'unit_price' => Money::ofMinor($unitPriceCents, 'USD'),
                'subtotal' => Money::ofMinor($subtotalCents, 'USD'),
                'discount' => Money::ofMinor(0, 'USD'),
                'fees' => Money::ofMinor($feesCents, 'USD'),
                'total' => Money::ofMinor($totalCents, 'USD'),
                'covers_fees' => $coversFees,
                'status' => 'pending',
            ]));
        });
    }

    private function generateOrderNumber(): string
    {
        return 'TKT-'.strtoupper(Str::random(8));
    }

    private function generateTicketNumber(): string
    {
        return strtoupper(Str::random(12));
    }
}
