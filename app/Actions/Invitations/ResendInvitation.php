<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use CorvMC\Membership\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class ResendInvitation
{
    use AsAction;

    /**
     * Resend an invitation to an email address.
     */
    public function handle(string $email): Invitation
    {
        $invitations = Invitation::withoutGlobalScopes()->forEmail($email)->get();

        if ($invitations->some(fn ($inv) => $inv->isUsed())) {
            throw new \Exception('User has already accepted invitation.');
        }

        $invitations = $invitations->filter(fn ($inv) => ! $inv->isUsed());
        if ($invitations->isEmpty()) {
            throw new \Exception('No invitations found for this email.');
        }

        $lastInvite = $invitations->last();

        // Delete existing invitations to avoid unique constraint violation
        $invitations->each(fn ($inv) => $inv->delete());

        $newInvite = Invitation::create([
            'email' => $email,
            'message' => $lastInvite->message,
            'inviter_id' => Auth::user()?->id,
        ]);

        // Send invitation notification
        Notification::route('mail', $email)
            ->notify(new UserInvitationNotification($newInvite, ['message' => $newInvite->message]));

        $newInvite->markAsSent();

        return $newInvite;
    }
}
