<?php

namespace App\Actions\MemberBenefits;

use App\Actions\Credits\AllocateMonthlyCredits;
use App\Enums\CreditType;
use App\Models\Reservation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AllocateUserMonthlyCredits
{
    use AsAction;

    /**
     * Allocate monthly credits to a sustaining member.
     *
     * This should be called when a user becomes a sustaining member
     * or at the start of each billing period.
     *
     * @param  User  $user  The user to allocate credits to
     * @param  int|null  $subscriptionAmountInCents  Optional subscription amount in cents (from verified payment metadata)
     */
    public function handle(User $user, ?int $subscriptionAmountInCents = null): void
    {
        if (! $user->isSustainingMember()) {
            return;
        }

        $hours = GetUserMonthlyFreeHours::run($user, $subscriptionAmountInCents);
        $blocks = Reservation::hoursToBlocks($hours);

        // Use AllocateMonthlyCredits to handle the allocation (handles reset logic)
        AllocateMonthlyCredits::run($user, $blocks, CreditType::FreeHours);
    }
}
