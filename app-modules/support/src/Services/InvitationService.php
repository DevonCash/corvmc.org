<?php

namespace CorvMC\Support\Services;

use App\Models\User;
use CorvMC\Support\Contracts\InvitationSubject;
use CorvMC\Support\Events\InvitationAccepted;
use CorvMC\Support\Events\InvitationCreated;
use CorvMC\Support\Events\InvitationDeclined;
use CorvMC\Support\Models\Invitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvitationService
{
    /**
     * Create an invitation for a user to respond to a subject.
     *
     * For self-invites (no inviter, subject allows it), the invitation
     * is created with accepted status immediately — e.g. event RSVPs.
     */
    public function invite(InvitationSubject $subject, User $invitee, ?User $inviter = null, array $data = []): Invitation
    {
        if (! $subject->acceptsInvitations()) {
            throw new InvalidArgumentException('This subject is not currently accepting invitations.');
        }

        if (! $subject->isInvitable($invitee)) {
            throw new InvalidArgumentException('This user is not eligible for this invitation.');
        }

        $isSelfInvite = $inviter === null;

        if ($isSelfInvite && ! $subject->allowsSelfInvite()) {
            throw new InvalidArgumentException('Self-invitations are not allowed for this subject.');
        }

        return DB::transaction(function () use ($subject, $invitee, $inviter, $data, $isSelfInvite) {
            $invitation = Invitation::create([
                'inviter_id' => $inviter?->id,
                'user_id' => $invitee->id,
                'invitable_type' => $subject->getMorphClass(),
                'invitable_id' => $subject->getKey(),
                'status' => $isSelfInvite ? 'accepted' : 'pending',
                'data' => $data ?: null,
                'responded_at' => $isSelfInvite ? now() : null,
            ]);

            InvitationCreated::dispatch($invitation);

            if ($isSelfInvite) {
                $subject->onInvitationAccepted($invitation);
                InvitationAccepted::dispatch($invitation);
            }

            return $invitation;
        });
    }

    /**
     * Accept an invitation.
     *
     * Valid from pending or declined (change of mind).
     */
    public function accept(Invitation $invitation): void
    {
        if (! in_array($invitation->status, ['pending', 'declined'])) {
            throw new InvalidArgumentException("Cannot accept an invitation with status '{$invitation->status}'.");
        }

        DB::transaction(function () use ($invitation) {
            $invitation->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $invitation->invitable->onInvitationAccepted($invitation);
            InvitationAccepted::dispatch($invitation);
        });
    }

    /**
     * Decline an invitation.
     *
     * Valid from pending or accepted (change of mind).
     * When declining a previously accepted invitation, onInvitationRevoked
     * is called first to undo the accept side effects (e.g. remove from pivot).
     */
    public function decline(Invitation $invitation): void
    {
        if (! in_array($invitation->status, ['pending', 'accepted'])) {
            throw new InvalidArgumentException("Cannot decline an invitation with status '{$invitation->status}'.");
        }

        $wasAccepted = $invitation->isAccepted();

        DB::transaction(function () use ($invitation, $wasAccepted) {
            $invitation->update([
                'status' => 'declined',
                'responded_at' => now(),
            ]);

            if ($wasAccepted) {
                $invitation->invitable->onInvitationRevoked($invitation);
            }

            $invitation->invitable->onInvitationDeclined($invitation);
            InvitationDeclined::dispatch($invitation);
        });
    }

    /**
     * Create pending invitations for all eligible users of a subject.
     *
     * Used for rehearsal attendance: band admin prompts all members at once.
     * Idempotent — skips users who already have an invitation for this subject.
     */
    public function promptGroup(InvitationSubject $subject, User $inviter, ?Collection $excludeUsers = null): Collection
    {
        if (! $subject->acceptsInvitations()) {
            throw new InvalidArgumentException('This subject is not currently accepting invitations.');
        }

        $eligible = $subject->eligibleUsers();
        if ($eligible === null) {
            throw new InvalidArgumentException('Cannot prompt a group when eligible users are unrestricted.');
        }

        $existingUserIds = $subject->invitations()->pluck('user_id');
        $excludeIds = $excludeUsers?->pluck('id') ?? collect();

        $usersToInvite = $eligible->reject(function (User $user) use ($existingUserIds, $excludeIds) {
            return $existingUserIds->contains($user->id) || $excludeIds->contains($user->id);
        });

        return DB::transaction(function () use ($subject, $inviter, $usersToInvite) {
            return $usersToInvite->map(function (User $user) use ($subject, $inviter) {
                $invitation = Invitation::create([
                    'inviter_id' => $inviter->id,
                    'user_id' => $user->id,
                    'invitable_type' => $subject->getMorphClass(),
                    'invitable_id' => $subject->getKey(),
                    'status' => 'pending',
                ]);

                InvitationCreated::dispatch($invitation);

                return $invitation;
            });
        });
    }

    /**
     * Retract a pending invitation (inviter takes it back before invitee responds).
     */
    public function retract(Invitation $invitation): void
    {
        if (! $invitation->isPending()) {
            throw new InvalidArgumentException('Only pending invitations can be retracted.');
        }

        $invitation->delete();
    }
}
