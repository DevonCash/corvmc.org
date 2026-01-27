<?php

namespace CorvMC\Events\Actions\Tickets;

use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Events\Notifications\TicketRefundNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Cashier;
use Lorisleiva\Actions\Concerns\AsAction;
use Stripe\Refund;
use Stripe\StripeClient;

class RefundTicketOrder
{
    use AsAction;

    /**
     * Refund a ticket order.
     *
     * @param  TicketOrder  $order  The order to refund
     * @param  string|null  $reason  Reason for the refund
     * @param  bool  $sendNotification  Whether to send refund notification
     * @return bool Whether the refund was successful
     *
     * @throws \RuntimeException If order cannot be refunded
     */
    public function handle(TicketOrder $order, ?string $reason = null, bool $sendNotification = true): bool
    {
        // Validate order can be refunded
        if (!$order->canRefund()) {
            throw new \RuntimeException('Order cannot be refunded: status is '.$order->status->label());
        }

        try {
            return DB::transaction(function () use ($order, $reason, $sendNotification) {
                // Process Stripe refund if paid via Stripe
                if ($order->payment_method === 'stripe' && $order->charge?->stripe_session_id) {
                    $this->processStripeRefund($order);
                }

                // Mark order as refunded
                $order->markAsRefunded($reason);

                // Update the associated Charge record
                $order->charge?->markAsRefunded($reason);

                // Cancel all tickets
                $order->tickets()->update(['status' => TicketStatus::Cancelled]);

                // Restore event inventory
                $order->event->decrementTicketsSold($order->quantity);

                Log::info('Successfully refunded ticket order', [
                    'order_id' => $order->id,
                    'event_id' => $order->event_id,
                    'quantity' => $order->quantity,
                    'reason' => $reason,
                ]);

                // Send notification after transaction commits
                if ($sendNotification) {
                    $this->scheduleRefundNotification($order, $reason);
                }

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Error refunding ticket order', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            throw $e;
        }
    }

    /**
     * Process the Stripe refund.
     */
    private function processStripeRefund(TicketOrder $order): void
    {
        $stripe = new StripeClient(config('cashier.secret'));

        // Get the payment intent from the checkout session
        $session = $stripe->checkout->sessions->retrieve($order->charge->stripe_session_id);

        if ($session->payment_intent) {
            $stripe->refunds->create([
                'payment_intent' => $session->payment_intent,
            ]);
        }
    }

    /**
     * Schedule refund notification to be sent after transaction commits.
     */
    private function scheduleRefundNotification(TicketOrder $order, ?string $reason): void
    {
        DB::afterCommit(function () use ($order, $reason) {
            try {
                $email = $order->getPurchaserEmail();

                if (!$email) {
                    return;
                }

                if ($order->user) {
                    $order->user->notify(new TicketRefundNotification($order, $reason));
                } else {
                    Notification::route('mail', $email)
                        ->notify(new TicketRefundNotification($order, $reason));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send ticket refund notification', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
