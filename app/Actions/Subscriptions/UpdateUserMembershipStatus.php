<?php

namespace App\Actions\Subscriptions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUserMembershipStatus
{
    use AsAction;

    /**
     * Update user membership status based on current Stripe subscriptions only.
     */
    public function handle(User $user): void
    {
        // Check if user has active subscription above threshold
        $shouldBeSustainingMember = (bool) GetActiveSubscription::run($user);

        // Update role accordingly
        if ($shouldBeSustainingMember && ! $user->isSustainingMember()) {
            $user->makeSustainingMember();
            \Log::info('Assigned sustaining member role via Stripe subscription', ['user_id' => $user->id]);
        } elseif (! $shouldBeSustainingMember && $user->isSustainingMember()) {
            $user->removeSustainingMember();
            \Log::info('Removed sustaining member role - no qualifying Stripe subscription', ['user_id' => $user->id]);
        }

        // Clear cached membership status
        Cache::forget("user.{$user->id}.is_sustaining");
    }
}
