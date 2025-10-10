<?php

namespace App\Actions\MemberBenefits;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMembershipTier
{
    use AsAction;

    /**
     * Get member tier based on subscription amount.
     *
     * Returns null if no active subscription.
     * Tiers: suggested_10, suggested_25, suggested_50, or custom
     */
    public function handle(User $user): ?string
    {
        $subscription = \App\Actions\Subscriptions\GetActiveSubscription::run($user);

        if (!$subscription) {
            return null;
        }

        try {
            // Get the Stripe subscription object with pricing info
            $stripeSubscription = $subscription->asStripeSubscription();
            $firstItem = $stripeSubscription->items->data[0];
            $amount = $firstItem->price->unit_amount / 100; // Convert from cents to dollars
        } catch (\Exception $e) {
            Log::warning('Failed to get subscription amount for tier calculation', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return 'custom';
        }

        return match (true) {
            $amount >= 45 && $amount <= 55 => 'suggested_50',
            $amount >= 20 && $amount <= 30 => 'suggested_25',
            $amount >= 8 && $amount <= 12 => 'suggested_10',
            default => 'custom'
        };
    }
}
