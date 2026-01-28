<?php

namespace CorvMC\Membership\Concerns;

trait HasMembershipStatus
{
    public function getSustainingMemberRole(): string
    {
        return $this->sustainingMemberRole ?? config('membership.member_role', 'sustaining member');
    }

    /**
     * Grant sustaining member status to the user.
     */
    public function makeSustainingMember(): self
    {
        return $this->assignRole($this->getSustainingMemberRole());
    }

    /**
     * Remove sustaining member status from the user.
     */
    public function removeSustainingMember(): self
    {
        return $this->removeRole($this->getSustainingMemberRole());
    }

    /**
     * Check if the user has sustaining member status.
     */
    public function isSustainingMember(): bool
    {
        return $this->hasRole($this->getSustainingMemberRole());
    }
}
