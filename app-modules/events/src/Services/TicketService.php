<?php

namespace CorvMC\Events\Services;

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Ticket;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Service for managing event tickets and ticket orders.
 * 
 * This service handles ticket sales, orders, refunds, and door sales.
 */
class TicketService
{
    /**
     * Create a ticket order for an event.
     *
     * @param Event $event The event to create tickets for
     * @param User $purchaser The user purchasing tickets
     * @param int $quantity Number of tickets to purchase
     * @param array $additionalData Optional additional order data
     * @return TicketOrder The created ticket order
     * @throws \Exception if not enough tickets available
     */
    public function createOrder(Event $event, User $purchaser, int $quantity, array $additionalData = []): TicketOrder
    {
        // Check ticket availability
        $availableTickets = $event->available_tickets;
        if ($availableTickets !== null && $quantity > $availableTickets) {
            throw new \Exception("Only {$availableTickets} tickets available for this event");
        }

        return DB::transaction(function () use ($event, $purchaser, $quantity, $additionalData) {
            // Calculate total amount
            $ticketPrice = $event->ticket_price ?? 0;
            $totalAmount = $ticketPrice * $quantity;

            // Create the order
            $order = TicketOrder::create(array_merge([
                'event_id' => $event->id,
                'purchaser_id' => $purchaser->id,
                'quantity' => $quantity,
                'unit_price' => $ticketPrice,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'order_number' => $this->generateOrderNumber(),
            ], $additionalData));

            // Create charge record if there's a cost
            if ($totalAmount > 0) {
                Charge::create([
                    'user_id' => $purchaser->id,
                    'chargeable_type' => TicketOrder::class,
                    'chargeable_id' => $order->id,
                    'amount' => $totalAmount,
                    'description' => "Tickets for {$event->title}",
                    'status' => ChargeStatus::Pending,
                ]);
            }

            return $order;
        });
    }

    /**
     * Process checkout for a ticket order.
     *
     * @param TicketOrder $order The order to process checkout for
     * @param string $successUrl URL to redirect on success
     * @param string $cancelUrl URL to redirect on cancel
     * @return array Checkout session data
     */
    public function processCheckout(TicketOrder $order, string $successUrl, string $cancelUrl): array
    {
        if ($order->total_amount <= 0) {
            // Free tickets - complete immediately
            $this->completeOrder($order);
            return [
                'success' => true,
                'redirect_url' => $successUrl,
            ];
        }

        // Create Stripe checkout session
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => "Tickets for {$order->event->title}",
                        'description' => "{$order->quantity} ticket(s)",
                    ],
                    'unit_amount' => $order->unit_price * 100, // Convert to cents
                ],
                'quantity' => $order->quantity,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'ticket_order_id' => $order->id,
            ],
        ]);

        // Update order with payment intent
        $order->update([
            'stripe_session_id' => $session->id,
            'stripe_payment_intent' => $session->payment_intent,
        ]);

        // Update charge with payment intent
        if ($charge = $order->charge) {
            $charge->update([
                'stripe_payment_intent' => $session->payment_intent,
            ]);
        }

        return [
            'success' => true,
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ];
    }

    /**
     * Complete a ticket order after successful payment.
     *
     * @param TicketOrder $order The order to complete
     * @param string|null $paymentIntentId Optional Stripe payment intent ID
     * @return TicketOrder The completed order
     */
    public function completeOrder(TicketOrder $order, ?string $paymentIntentId = null): TicketOrder
    {
        return DB::transaction(function () use ($order, $paymentIntentId) {
            // Update order status
            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'stripe_payment_intent' => $paymentIntentId ?: $order->stripe_payment_intent,
            ]);

            // Update charge status
            if ($charge = $order->charge) {
                $charge->update([
                    'status' => ChargeStatus::Paid,
                    'stripe_payment_intent' => $paymentIntentId ?: $charge->stripe_payment_intent,
                ]);
            }

            // Generate tickets
            $this->generateTickets($order);

            return $order->fresh();
        });
    }

    /**
     * Generate tickets for a completed order.
     *
     * @param TicketOrder $order The order to generate tickets for
     * @return Collection The generated tickets
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

    /**
     * Create a door sale (in-person ticket sale).
     *
     * @param Event $event The event to sell tickets for
     * @param User $seller The staff member making the sale
     * @param int $quantity Number of tickets
     * @param float|null $priceOverride Optional price override
     * @param string|null $buyerName Optional buyer name for record keeping
     * @return TicketOrder The created door sale order
     */
    public function createDoorSale(
        Event $event, 
        User $seller, 
        int $quantity, 
        ?float $priceOverride = null,
        ?string $buyerName = null
    ): TicketOrder {
        return DB::transaction(function () use ($event, $seller, $quantity, $priceOverride, $buyerName) {
            $unitPrice = $priceOverride ?? $event->ticket_price ?? 0;
            $totalAmount = $unitPrice * $quantity;

            // Create the order
            $order = TicketOrder::create([
                'event_id' => $event->id,
                'purchaser_id' => $seller->id, // Staff member who made the sale
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'completed_at' => now(),
                'order_number' => $this->generateOrderNumber(),
                'is_door_sale' => true,
                'buyer_name' => $buyerName,
                'notes' => "Door sale by {$seller->name}",
            ]);

            // Generate tickets immediately for door sales
            $this->generateTickets($order);

            // Create completed charge record
            if ($totalAmount > 0) {
                Charge::create([
                    'user_id' => $seller->id,
                    'chargeable_type' => TicketOrder::class,
                    'chargeable_id' => $order->id,
                    'amount' => $totalAmount,
                    'description' => "Door sale tickets for {$event->title}",
                    'status' => ChargeStatus::Paid,
                    'payment_method' => 'cash', // Assume cash for door sales
                ]);
            }

            return $order;
        });
    }

    /**
     * Refund a ticket order.
     *
     * @param TicketOrder $order The order to refund
     * @param string|null $reason Optional refund reason
     * @return TicketOrder The refunded order
     */
    public function refundOrder(TicketOrder $order, ?string $reason = null): TicketOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            // Process Stripe refund if applicable
            if ($order->stripe_payment_intent && $order->total_amount > 0) {
                Stripe::setApiKey(config('services.stripe.secret'));

                $refund = \Stripe\Refund::create([
                    'payment_intent' => $order->stripe_payment_intent,
                    'reason' => 'requested_by_customer',
                ]);

                $order->update([
                    'stripe_refund_id' => $refund->id,
                ]);
            }

            // Update order status
            $order->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_reason' => $reason,
            ]);

            // Update charge status
            if ($charge = $order->charge) {
                $charge->update([
                    'status' => ChargeStatus::Refunded,
                ]);
            }

            // Void all tickets
            $order->tickets()->update(['status' => 'voided']);

            return $order->fresh();
        });
    }

    /**
     * Cancel a pending ticket order.
     *
     * @param TicketOrder $order The order to cancel
     * @param string|null $reason Optional cancellation reason
     * @return TicketOrder The cancelled order
     */
    public function cancelOrder(TicketOrder $order, ?string $reason = null): TicketOrder
    {
        if ($order->status !== 'pending') {
            throw new \Exception('Only pending orders can be cancelled');
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        // Update charge status if exists
        if ($charge = $order->charge) {
            $charge->update([
                'status' => ChargeStatus::Cancelled,
            ]);
        }

        return $order;
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(8));
        } while (TicketOrder::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Generate a unique ticket number.
     *
     * @return string
     */
    protected function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-' . strtoupper(Str::random(10));
        } while (Ticket::where('ticket_number', $number)->exists());

        return $number;
    }
}