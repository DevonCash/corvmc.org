<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use CorvMC\Membership\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUserMembershipStatus
{
    use AsAction;

    /**
     * Update user membership status based on Stripe subscription state.
     *
     * This checks both our DB (fast) and Stripe API (authoritative) to prevent
     * removing benefits from users who paid but experienced a sync failure.
     *
     * Priority: Prevent removing benefits from paying customers over accidentally
     * granting benefits to non-paying users.
     */
    public function handle(User $user): void
    {
        // First check our DB (fast)
        $hasSubscriptionInDb = $user->subscription();

        // If DB says they have a subscription, trust it and keep role
        if ($hasSubscriptionInDb?->active()) {
            if (! $user->isSustainingMember()) {
                $user->makeSustainingMember();
                Log::info('Assigned sustaining member role via DB subscription', ['user_id' => $user->id]);
            }
            Cache::forget("user.{$user->id}.is_sustaining");

            return;
        }

        // DB says no subscription, but check Stripe API to be sure
        // This prevents removing benefits if Cashier sync failed
        if ($user->stripe_id) {
            try {
                $stripeSubscriptions = \Laravel\Cashier\Cashier::stripe()->subscriptions->all([
                    'customer' => $user->stripe_id,
                    'status' => 'active',
                    'limit' => 1,
                ]);

                if ($stripeSubscriptions->data && count($stripeSubscriptions->data) > 0) {
                    // Subscription exists in Stripe but not in our DB - sync issue!
                    Log::warning('Subscription sync mismatch - exists in Stripe but not DB', [
                        'user_id' => $user->id,
                        'stripe_id' => $user->stripe_id,
                        'stripe_subscription_id' => $stripeSubscriptions->data[0]->id,
                    ]);

                    // Keep the role - user has paid
                    if (! $user->isSustainingMember()) {
                        $user->makeSustainingMember();
                        Log::info('Assigned sustaining member role via Stripe API (DB sync failed)', [
                            'user_id' => $user->id,
                        ]);
                    }

                    Cache::forget("user.{$user->id}.is_sustaining");

                    return;
                }
            } catch (\Exception $e) {
                Log::error('Failed to query Stripe API for subscription status', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                // On API error, be conservative - don't remove benefits
                return;
            }
        }

        // Confirmed: No subscription in DB or Stripe - safe to remove role
        if ($user->isSustainingMember()) {
            $user->removeSustainingMember();
            Log::info('Removed sustaining member role - no subscription in DB or Stripe', [
                'user_id' => $user->id,
            ]);
        }

        Cache::forget("user.{$user->id}.is_sustaining");
    }
}
