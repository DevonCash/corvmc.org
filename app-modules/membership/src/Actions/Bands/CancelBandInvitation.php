<?php

namespace CorvMC\Membership\Actions\Bands;

use App\Exceptions\BandException;
use CorvMC\Membership\Models\Band;
use CorvMC\Membership\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelBandInvitation
{
    use AsAction;

    /**
     * Cancel a pending band invitation.
     */
    public function handle(Band $band, User $user): void
    {
        $membership = $band->memberships()->invited()->where('user_id', $user->id)->first();

        if (! $membership) {
            throw BandException::userNotInvited();
        }

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
            ->modalDescription(fn ($record) => "Cancel the invitation for {$record->user->name}?")
            ->authorize('cancel')
            ->action(function ($record): void {
                static::run($record->band, $record->user);

                Notification::make()
                    ->title('Invitation cancelled')
                    ->body("Invitation for {$record->user->name} has been cancelled")
                    ->success()
                    ->send();
            });
    }
}
