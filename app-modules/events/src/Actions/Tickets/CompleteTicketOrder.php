<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Events\Notifications\TicketPurchaseConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteTicketOrder
{
    use AsAction;

    /**
     * Complete a ticket order after successful payment.
     *
     * This is called from both the checkout success redirect and Stripe webhooks.
     * Idempotent - safe to call multiple times for the same order.
     *
     * @param  int  $orderId  The ticket order ID from metadata
     * @param  string  $sessionId  The Stripe checkout session ID
     * @return bool Whether processing was successful
     */
    public function handle(int $orderId, string $sessionId): bool
    {
        try {
            $order = TicketOrder::find($orderId);

            if (!$order) {
                Log::error('Ticket order not found for checkout', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                ]);

                return false;
            }

            // Idempotency check - skip if already completed
            if ($order->isCompleted()) {
                Log::info('Ticket order already completed, skipping', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId,
                ]);

                return true;
            }

            return DB::transaction(function () use ($order, $sessionId) {
                // Mark order as completed
                $order->markAsCompleted('stripe');

                // Update the associated Charge record (if exists)
                $order->charge?->markAsPaid('stripe', $sessionId, 'Paid via Stripe checkout');

                // Generate individual tickets
                GenerateTickets::run($order);

                // Update event's tickets sold count
                $order->event->incrementTicketsSold($order->quantity);

                Log::info('Successfully completed ticket order', [
                    'order_id' => $order->id,
                    'event_id' => $order->event_id,
                    'quantity' => $order->quantity,
                    'session_id' => $sessionId,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Error completing ticket order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'session_id' => $sessionId,
            ]);

            return false;
        }
    }

    /**
     * Handle post-transaction side effects.
     */
    public function afterCommit(TicketOrder $order): void
    {
        // Send confirmation email outside transaction
        try {
            $this->sendConfirmationNotification($order);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket confirmation email', [
                'order_id' => $order->id,
                'email' => $order->getPurchaserEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send confirmation notification to the purchaser.
     */
    private function sendConfirmationNotification(TicketOrder $order): void
    {
        $email = $order->getPurchaserEmail();

        if (!$email) {
            Log::warning('No email for ticket order confirmation', ['order_id' => $order->id]);

            return;
        }

        if ($order->user) {
            // Send to authenticated user
            $order->user->notify(new TicketPurchaseConfirmation($order));
        } else {
            // Send to guest email
            Notification::route('mail', $email)
                ->notify(new TicketPurchaseConfirmation($order));
        }
    }
}
