<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelBandInvitation
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Cancel a pending band invitation.
     */
    public function handle(Band $band, User $user): void
    {
        $membership = $band->memberships()->where('user_id', $user->id)->first();

        if (!$membership) {
            throw BandException::userNotFound();
        }

        if ($membership->status !== 'invited') {
            throw BandException::invitationNotPending();
        }

        // Remove the invitation
        $membership->delete();
    }

    public static function filamentAction(): Action
    {
        return Action::make('cancel_invitation')
            ->label('Cancel')
            ->color('danger')
            ->icon('tabler-x')
            ->requiresConfirmation()
            ->modalHeading('Cancel Band Invitation')
            ->modalDescription(fn($record) => "Cancel the invitation for {$record->name}?")
            ->authorize('cancel')
            ->action(function ($record): void {
                static::run($record->band, $record->user);

                Notification::make()
                    ->title('Invitation cancelled')
                    ->body("Invitation for {$record->name} has been cancelled")
                    ->success()
                    ->send();
            });
    }
}
