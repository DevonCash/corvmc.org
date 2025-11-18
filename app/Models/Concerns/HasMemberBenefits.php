<?php

namespace App\Models\Concerns;

use Laravel\Cashier\Billable;

trait HasMemberBenefits
{
    use Billable;

    /**
     * Determine if the user is a sustaining member.
     */
    public function isSustainingMember(): bool
    {
        return $this->membership_type === 'sustaining';
    }

    public function getMonthlyFreeHours(): int {
        if(!$this->isSustainingMember()) {
            return 0;
        }

        if($this->subscribed()) {

        }
    }

    public function calculateFreeHours(int $contributionAmountInCents): int {
        // For every $5 contributed, the user gets 1 free hour
        return floor($contributionAmountInCents / 500);
    }
}
