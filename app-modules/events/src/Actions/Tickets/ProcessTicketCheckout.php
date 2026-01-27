<?php

namespace CorvMC\Events\Actions\Tickets;

use App\Models\User;
use CorvMC\Events\Models\TicketOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessTicketCheckout
{
    use AsAction;

    /**
     * Create a Stripe checkout session for a ticket order.
     *
     * @param  TicketOrder  $order  The order to process
     * @return \Laravel\Cashier\Checkout The Cashier checkout object
     *
     * @throws \Exception If checkout cannot be created
     */
    public function handle(TicketOrder $order)
    {
        // Get the billable user (or create a temporary one for guests)
        $user = $order->user;

        if ($user) {
            // Ensure authenticated user has a Stripe customer ID
            if (!$user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            return $this->createAuthenticatedCheckout($order, $user);
        }

        // Guest checkout - use Stripe's guest mode
        return $this->createGuestCheckout($order);
    }

    /**
     * Create checkout session for authenticated user.
     * Note: Don't pass customer_email since Cashier automatically sets the customer ID.
     */
    private function createAuthenticatedCheckout(TicketOrder $order, User $user)
    {
        $lineItems = $this->buildLineItems($order);

        return $user->checkout($lineItems, [
            'success_url' => route('tickets.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('tickets.checkout.cancel', ['order' => $order->uuid]),
            'metadata' => $this->buildMetadata($order),
            'payment_intent_data' => [
                'metadata' => $this->buildMetadata($order),
            ],
        ]);
    }

    /**
     * Create checkout session for guest (unauthenticated) user.
     */
    private function createGuestCheckout(TicketOrder $order)
    {
        $lineItems = $this->buildStripeLineItems($order);

        // Use the Stripe client from Cashier to ensure API key is set
        $stripe = \Laravel\Cashier\Cashier::stripe();

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => route('tickets.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('tickets.checkout.cancel', ['order' => $order->uuid]),
            'customer_email' => $order->email,
            'line_items' => $lineItems,
            'metadata' => $this->buildMetadata($order),
            'payment_intent_data' => [
                'metadata' => $this->buildMetadata($order),
            ],
        ]);

        // Return a simple object with the URL for redirect
        return (object) ['url' => $session->url];
    }

    /**
     * Build line items for Cashier checkout (uses price IDs or inline pricing).
     */
    private function buildLineItems(TicketOrder $order): array
    {
        $priceInCents = $order->total->getMinorAmount()->toInt();

        // Use inline pricing (no pre-created Stripe prices needed)
        return [
            [
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $priceInCents,
                    'product_data' => [
                        'name' => config('ticketing.stripe_product_name', 'Event Ticket'),
                        'description' => $order->getChargeableDescription(),
                    ],
                ],
                'quantity' => 1, // We bundle quantity into the total price
            ],
        ];
    }

    /**
     * Build line items for direct Stripe API call (guests).
     */
    private function buildStripeLineItems(TicketOrder $order): array
    {
        $priceInCents = $order->total->getMinorAmount()->toInt();

        return [
            [
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $priceInCents,
                    'product_data' => [
                        'name' => config('ticketing.stripe_product_name', 'Event Ticket'),
                        'description' => $order->getChargeableDescription(),
                    ],
                ],
                'quantity' => 1,
            ],
        ];
    }

    /**
     * Build metadata for Stripe checkout session.
     */
    private function buildMetadata(TicketOrder $order): array
    {
        return [
            'type' => 'ticket_order',
            'ticket_order_id' => $order->id,
            'ticket_order_uuid' => $order->uuid,
            'event_id' => $order->event_id,
            'user_id' => $order->user_id,
            'quantity' => $order->quantity,
        ];
    }
}
