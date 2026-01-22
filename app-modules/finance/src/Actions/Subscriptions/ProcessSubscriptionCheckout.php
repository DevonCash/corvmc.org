<?php

namespace CorvMC\Finance\Actions\Subscriptions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSubscriptionCheckout
{
    use AsAction;

    /**
     * Process a successful subscription checkout.
     *
     * Called when we have verified confirmation from Stripe that a subscription payment succeeded.
     * This assigns the sustaining member role immediately and allocates credits.
     *
     * This action trusts that the caller has already verified the payment with Stripe's API.
     * It does NOT check the database for subscription state - that's what UpdateUserMembershipStatus is for.
     *
     * Idempotent - safe to call multiple times for the same user.
     *
     * @param  int  $userId  The user ID from metadata
     * @param  string  $sessionId  The Stripe checkout session ID
     * @param  array  $metadata  Additional metadata from the checkout session
     * @return bool Whether processing was successful
     */
    public function handle(int $userId, string $sessionId, array $metadata = []): bool
    {
        try {
            if (! $userId) {
                Log::warning('No user ID provided for subscription checkout processing', ['session_id' => $sessionId]);

                return false;
            }

            $user = User::find($userId);

            if (! $user) {
                Log::error('User not found for subscription checkout', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                ]);

                return false;
            }

            // Assign sustaining member role immediately
            // (idempotent - only assigns if not already assigned)
            if (! $user->isSustainingMember()) {
                $user->makeSustainingMember();
                Log::info('Assigned sustaining member role from verified Stripe payment', [
                    'user_id' => $user->id,
                    'session_id' => $sessionId,
                ]);
            }

            // Allocate monthly credits based on the subscription amount from metadata
            // (idempotent - won't double-allocate in same month)
            // Pass cents (integer) to maintain precision
            $baseAmountInCents = $metadata['base_amount'] ?? null;
            \CorvMC\Finance\Actions\MemberBenefits\AllocateUserMonthlyCredits::run($user, $baseAmountInCents);

            Log::info('Successfully processed subscription checkout', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'base_amount' => $metadata['base_amount'] ?? null,
                'covers_fees' => $metadata['covers_fees'] ?? null,
                'is_sustaining_member' => $user->isSustainingMember(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing subscription checkout', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'session_id' => $sessionId,
                'metadata' => $metadata,
            ]);

            return false;
        }
    }
}
