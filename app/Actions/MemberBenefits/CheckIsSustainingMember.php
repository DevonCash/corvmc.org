<?php

namespace App\Actions\MemberBenefits;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckIsSustainingMember
{
    use AsAction;

    /**
     * Check if a user qualifies as a sustaining member.
     *
     * Currently based on role assignment.
     * Could be extended to check active subscriptions in the future.
     */
    public function handle(User $user): bool
    {
        return $user->hasRole('sustaining member');
    }
}
