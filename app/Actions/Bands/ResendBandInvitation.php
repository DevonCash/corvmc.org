<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use App\Notifications\BandInvitationNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class ResendBandInvitation
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Resend an invitation to a user.
     */
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->for($user)->exists()) {
            throw BandException::userNotInvited();
        }

        // Update the invited_at timestamp
        $band->members()->updateExistingPivot($user->id, [
            'invited_at' => now(),
        ]);

        // Get the current invitation details
        $member = $band->members()->wherePivot('user_id', $user->id)->first();

        // Resend notification
        $user->notify(new BandInvitationNotification(
            $band,
            $member->pivot->role,
            $member->pivot->position
        ));
    }

    public static function filamentAction(): Action
    {
        return Action::make('resend_invitation')
            ->label('Resend')
            ->color('warning')
            ->icon('tabler-mail-forward')
            ->authorize('resend')
            ->action(function ($record): void {
                static::run($record->band, $record->user);

                Notification::make()
                    ->title('Invitation resent')
                    ->body("Invitation resent to {$record->user->name}")
                    ->success()
                    ->send();
            });
    }
}
