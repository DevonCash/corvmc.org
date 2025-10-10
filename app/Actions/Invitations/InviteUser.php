<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteUser
{
    use AsAction;

    public function handle(string $email, array $data = []): Invitation
    {
        return DB::transaction(function () use ($email, $data) {
            $invitation = GenerateInvitation::run($email, $data);

            Notification::route('mail', $email)
                ->notify(new UserInvitationNotification($invitation, $data));

            $invitation->markAsSent();

            return $invitation;
        });
    }
}
