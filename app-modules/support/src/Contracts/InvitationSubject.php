<?php

namespace CorvMC\Support\Contracts;

use App\Models\User;
use CorvMC\Support\Models\Invitation;
use Illuminate\Support\Collection;

/**
 * Implemented by models that can be the target of invitations.
 *
 * Same pattern as HasTimePeriod and Recurrable — subject models
 * implement the contract directly since the Invitation model lives
 * in the support module (a dependency of every other module).
 */
interface InvitationSubject
{
    /**
     * Whether this instance currently accepts invitations.
     */
    public function acceptsInvitations(): bool;

    /**
     * Whether a specific user can be invited to this subject.
     * Used by invite() for O(1) single-user checks.
     */
    public function isInvitable(User $user): bool;

    /**
     * All eligible users as a collection. Null means any authenticated member.
     * Used by promptGroup() for bulk invitations.
     */
    public function eligibleUsers(): ?Collection;

    /**
     * Whether users can invite themselves (e.g. event RSVPs)
     * vs. requiring an inviter (e.g. band membership).
     */
    public function allowsSelfInvite(): bool;

    /**
     * Side effects when an invitation is accepted.
     */
    public function onInvitationAccepted(Invitation $invitation): void;

    /**
     * Side effects when an invitation is declined.
     */
    public function onInvitationDeclined(Invitation $invitation): void;

    /**
     * Undo side effects when a previously accepted invitation is revoked
     * (e.g. user changes mind from accepted to declined).
     */
    public function onInvitationRevoked(Invitation $invitation): void;
}
