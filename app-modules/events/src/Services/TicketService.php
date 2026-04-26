<?php

namespace CorvMC\Events\Services;

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
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
     * Create a ticket order and its Finance Order.
     *
     * @return TicketOrder The created ticket order (with Finance Order committed)
     * @throws \Exception if not enough tickets available
     */
    public function createOrder(Event $event, User $purchaser, int $quantity, array $additionalData = []): TicketOrder
    {
        $availableTickets = $event->available_tickets;
        if ($availableTickets !== null && $quantity > $availableTickets) {
            throw new \Exception("Only {$availableTickets} tickets available for this event");
        }

        return DB::transaction(function () use ($event, $purchaser, $quantity, $additionalData) {
            $ticketPrice = $event->ticket_price ?? 0;
            $totalAmount = $ticketPrice * $quantity;

            $ticketOrder = TicketOrder::create(array_merge([
                'event_id' => $event->id,
                'purchaser_id' => $purchaser->id,
                'quantity' => $quantity,
                'unit_price' => $ticketPrice,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'order_number' => $this->generateOrderNumber(),
            ], $additionalData));

            return $ticketOrder;
        });
    }

    /**
     * Process checkout for a ticket order via the Finance Order system.
     *
     * Creates a Finance Order, prices it, commits with Stripe rail,
     * and returns the checkout URL.
     */
    public function processCheckout(TicketOrder $ticketOrder, string $successUrl, string $cancelUrl): array
    {
        // Guard against duplicate orders
        $existingOrder = Finance::findActiveOrder($ticketOrder);
        if ($existingOrder) {
            $checkoutUrl = $existingOrder->checkoutUrl();

            return [
                'success' => true,
                'checkout_url' => $checkoutUrl,
                'session_id' => null,
            ];
        }

        $user = $ticketOrder->user ?? User::find($ticketOrder->purchaser_id);

        // Price via Finance
        $lineItems = Finance::price([$ticketOrder], $user);
        $totalCents = (int) $lineItems->sum('amount');

        if ($totalCents <= 0) {
            // Free tickets — create Order, complete immediately, generate tickets
            $order = $this->createFinanceOrder($ticketOrder, $lineItems, $totalCents);
            Finance::commit($order->fresh(), []);
            $this->completeTicketOrder($ticketOrder);

            return [
                'success' => true,
                'redirect_url' => $successUrl,
            ];
        }

        // Create Finance Order and commit with Stripe
        $order = $this->createFinanceOrder($ticketOrder, $lineItems, $totalCents);
        $committed = Finance::commit($order->fresh(), ['stripe' => $totalCents]);

        $checkoutUrl = $committed->checkoutUrl();

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'session_id' => $committed->transactions->first()?->metadata['session_id'] ?? null,
        ];
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
                    ->whereState('status', \CorvMC\Finance\States\TransactionState\Pending::class)
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
        if (!$staffUser) {
            throw new \Exception('staffUser is required for door sales');
        }

        return DB::transaction(function () use ($event, $quantity, $paymentMethod, $staffUser, $priceOverride, $isSustainingMember, $attendeeName, $attendeeEmail, $notes) {
            // Get base unit price in cents (event ticket_price is in cents)
            $unitPrice = (int) ($priceOverride ?? ($event->ticket_price ?? 0));
            $subtotal = $unitPrice * $quantity;

            // Calculate discount if sustaining member
            $discount = 0;
            if ($isSustainingMember) {
                $discountPercent = config('ticketing.sustaining_member_discount', 50);
                $discount = (int) round($subtotal * $discountPercent / 100);
            }

            $total = $subtotal - $discount;

            $ticketOrder = TicketOrder::create([
                'event_id' => $event->id,
                'user_id' => $staffUser->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'fees' => 0,
                'total' => max($total, 0),
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
            if ($total > 0) {
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
    private function createFinanceOrder(TicketOrder $ticketOrder, \Illuminate\Support\Collection $lineItems, int $totalCents): Order
    {
        $order = Order::create([
            'user_id' => $ticketOrder->purchaser_id,
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

    private function generateOrderNumber(): string
    {
        return 'TKT-' . strtoupper(Str::random(8));
    }

    private function generateTicketNumber(): string
    {
        return strtoupper(Str::random(12));
    }
}
