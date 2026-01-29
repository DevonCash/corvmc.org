<?php

namespace CorvMC\Kiosk\Http\Controllers;

use CorvMC\Events\Actions\Tickets\CreateDoorSale;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Cashier\Cashier;

class DoorController extends Controller
{
    /**
     * Get pricing information for door sales.
     */
    public function pricing(Request $request, Event $event): JsonResponse
    {
        if (! $event->ticketing_enabled) {
            return response()->json([
                'message' => 'This event does not have ticketing enabled.',
            ], 404);
        }

        $basePrice = $event->getBaseTicketPrice()->getMinorAmount()->toInt();
        $memberDiscount = config('ticketing.sustaining_member_discount', 50);

        return response()->json([
            'base_price' => $basePrice,
            'member_discount' => $memberDiscount,
            'max_quantity' => 10,
            'event_id' => $event->id,
        ]);
    }

    /**
     * Create a door sale (cash or captured card payment).
     */
    public function createSale(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'payment_method' => 'required|string|in:cash,card',
            'is_sustaining_member' => 'boolean',
            'price_override' => 'nullable|integer|min:0',
            'attendee_name' => 'nullable|string|max:255',
            'attendee_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:500',
            'payment_intent_id' => 'nullable|string', // For card payments
        ]);

        if (! $event->ticketing_enabled) {
            return response()->json([
                'message' => 'This event does not have ticketing enabled.',
            ], 400);
        }

        // Check availability
        if (! $event->hasTicketsAvailable($request->quantity)) {
            return response()->json([
                'message' => 'Not enough tickets available.',
            ], 400);
        }

        try {
            $order = CreateDoorSale::run(
                event: $event,
                quantity: $request->quantity,
                paymentMethod: $request->payment_method,
                staffUser: $request->user(),
                priceOverride: $request->price_override,
                isSustainingMember: $request->boolean('is_sustaining_member'),
                attendeeName: $request->attendee_name,
                attendeeEmail: $request->attendee_email,
                notes: $request->notes
            );

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'quantity' => $order->quantity,
                    'total' => $order->total->getMinorAmount()->toInt(),
                    'payment_method' => $order->payment_method,
                ],
                'tickets' => $order->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                ])->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a PaymentIntent for Stripe Terminal collection.
     */
    public function createPaymentIntent(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'is_sustaining_member' => 'boolean',
            'price_override' => 'nullable|integer|min:0',
        ]);

        if (! $event->ticketing_enabled) {
            return response()->json([
                'message' => 'This event does not have ticketing enabled.',
            ], 400);
        }

        // Calculate the amount
        $basePrice = $event->getBaseTicketPrice()->getMinorAmount()->toInt();
        $subtotal = $basePrice * $request->quantity;
        $discount = 0;

        if ($request->boolean('is_sustaining_member')) {
            $discountPercent = config('ticketing.sustaining_member_discount', 50);
            $discount = (int) round($subtotal * $discountPercent / 100);
        }

        $total = $request->price_override ?? ($subtotal - $discount);

        if ($total <= 0) {
            return response()->json([
                'message' => 'Total amount must be greater than zero for card payments.',
            ], 400);
        }

        try {
            $stripe = Cashier::stripe();

            // Create a PaymentIntent for Terminal
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $total,
                'currency' => 'usd',
                'payment_method_types' => ['card_present'],
                'capture_method' => 'automatic',
                'metadata' => [
                    'event_id' => $event->id,
                    'quantity' => $request->quantity,
                    'is_sustaining_member' => $request->boolean('is_sustaining_member') ? 'true' : 'false',
                    'staff_user_id' => $request->user()->id,
                ],
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment intent: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Capture a payment and complete the sale (for Terminal payments).
     */
    public function capturePayment(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:10',
            'is_sustaining_member' => 'boolean',
            'price_override' => 'nullable|integer|min:0',
            'attendee_name' => 'nullable|string|max:255',
            'attendee_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            // Verify the PaymentIntent was successful
            $stripe = Cashier::stripe();
            $paymentIntent = $stripe->paymentIntents->retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment was not successful. Status: '.$paymentIntent->status,
                ], 400);
            }

            // Create the door sale
            $order = CreateDoorSale::run(
                event: $event,
                quantity: $request->quantity,
                paymentMethod: 'card',
                staffUser: $request->user(),
                priceOverride: $request->price_override,
                isSustainingMember: $request->boolean('is_sustaining_member'),
                attendeeName: $request->attendee_name,
                attendeeEmail: $request->attendee_email,
                notes: 'Terminal payment: '.$request->payment_intent_id.($request->notes ? ' - '.$request->notes : '')
            );

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'uuid' => $order->uuid,
                    'quantity' => $order->quantity,
                    'total' => $order->total->getMinorAmount()->toInt(),
                    'payment_method' => $order->payment_method,
                ],
                'tickets' => $order->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                ])->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete sale: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent sales for an event.
     */
    public function recentSales(Request $request, Event $event): JsonResponse
    {
        $limit = $request->query('limit', 10);

        $sales = TicketOrder::where('event_id', $event->id)
            ->where('is_door_sale', true)
            ->where('status', TicketOrderStatus::Completed)
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(fn (TicketOrder $order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'quantity' => $order->quantity,
                'total' => $order->total->getMinorAmount()->toInt(),
                'payment_method' => $order->payment_method,
                'name' => $order->getPurchaserName(),
                'completed_at' => $order->completed_at?->toIso8601String(),
            ]);

        return response()->json(['sales' => $sales]);
    }
}
