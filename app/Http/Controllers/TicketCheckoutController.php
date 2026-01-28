<?php

namespace App\Http\Controllers;

use CorvMC\Events\Actions\Tickets\CompleteTicketOrder;
use CorvMC\Events\Models\TicketOrder;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class TicketCheckoutController extends Controller
{
    /**
     * Handle successful ticket checkout.
     * Supports both authenticated and guest checkout.
     */
    public function success(Request $request)
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return redirect()->route('events.index')
                ->with('error', 'Invalid checkout session.');
        }

        try {
            // Retrieve the checkout session
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
            $metadata = $session->metadata ? $session->metadata->toArray() : [];

            // Verify this is a ticket order
            if (($metadata['type'] ?? '') !== 'ticket_order') {
                return redirect()->route('events.index')
                    ->with('error', 'Invalid checkout type.');
            }

            $orderId = $metadata['ticket_order_id'] ?? null;

            if (!$orderId) {
                return redirect()->route('events.index')
                    ->with('error', 'Order not found.');
            }

            // Process the ticket order completion
            if ($session->payment_status === 'paid') {
                CompleteTicketOrder::run((int) $orderId, $sessionId);

                // Send after-commit notification
                $order = TicketOrder::find($orderId);
                if ($order) {
                    CompleteTicketOrder::make()->afterCommit($order);
                }

                Log::info('Ticket checkout success', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                ]);
            } else {
                Log::info('Ticket checkout pending payment', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                    'payment_status' => $session->payment_status,
                ]);
            }

            // Find the order for the success page
            $order = TicketOrder::with('event')->find($orderId);

            if (!$order) {
                return redirect()->route('events.index')
                    ->with('success', 'Payment successful! Check your email for ticket details.');
            }

            // Render the success page
            return view('tickets.success', [
                'order' => $order,
                'event' => $order->event,
                'isGuest' => !auth()->check(),
                'isNewUser' => auth()->check() && !$order->user_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Ticket checkout success error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('events.index')
                ->with('info', 'Your payment is being processed. Check your email for confirmation.');
        }
    }

    /**
     * Handle cancelled ticket checkout.
     */
    public function cancel(Request $request, TicketOrder $order)
    {
        Log::info('Ticket checkout cancelled', [
            'order_id' => $order->id,
            'order_uuid' => $order->uuid,
        ]);

        // Cancel the pending order
        $order->markAsCancelled('User cancelled checkout');

        // Redirect back to event page
        return redirect()->route('events.show', $order->event)
            ->with('info', 'Checkout cancelled. You can try again anytime.');
    }
}
