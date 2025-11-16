<?php

namespace App\Actions\Subscriptions;

use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBillingPeriodPeakAmount
{
    use AsAction;

    /**
     * Get the highest amount charged for this subscription in the current billing period.
     */
    public function handle(Subscription $subscription): float
    {
        $stripeSubscription = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);
        $currentPeriodStart = $stripeSubscription->current_period_start;

        // Get all invoices for this subscription since the current period started
        $invoices = collect(Cashier::stripe()->invoices->all([
            'subscription' => $subscription->stripe_id,
            'created' => ['gte' => $currentPeriodStart],
            'limit' => 100,
        ])->data);

        return $invoices
            ->filter(fn ($invoice) => in_array($invoice->status, ['paid', 'open']))
            ->flatMap(fn ($invoice) => $invoice->lines->data)
            ->filter(fn ($line) => $line->subscription === $subscription->stripe_id)
            ->map(fn ($line) => $line->amount)
            ->sum() / 100;
    }
}
