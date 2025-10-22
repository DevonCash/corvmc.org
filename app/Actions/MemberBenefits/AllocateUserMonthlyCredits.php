<?php

namespace App\Actions\MemberBenefits;

use App\Actions\Credits\AllocateMonthlyCredits;
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
     */
    public function handle(User $user): void
    {
        if (!CheckIsSustainingMember::run($user)) {
            return;
        }

        $hours = GetUserMonthlyFreeHours::run($user);
        $blocks = Reservation::hoursToBlocks($hours);

        // Use AllocateMonthlyCredits to handle the allocation (handles reset logic)
        AllocateMonthlyCredits::run($user, $blocks, 'free_hours');
    }
}
