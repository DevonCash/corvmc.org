<?php

namespace CorvMC\Membership\Actions\Bands;

use App\Exceptions\BandException;
use CorvMC\Membership\Models\Band;
use CorvMC\Membership\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class DeclineBandInvitation
{
    use AsAction;

    /**
     * Decline an invitation to join a band.
     * Deletes the invitation record - band admins can re-invite if needed.
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
        return Action::make('decline_invitation')
            ->label('Decline')
            ->color('danger')
            ->icon('tabler-x')
            ->requiresConfirmation()
            ->modalHeading('Decline Band Invitation')
            ->modalDescription(fn ($record) => "Decline invitation to join {$record->band->name}?")
            ->action(function ($record): void {
                static::run($record->band, $record->user);

                Notification::make()
                    ->title('Invitation declined')
                    ->body('You have declined the invitation')
                    ->success()
                    ->send();
            })
            ->authorize('decline');
    }
}
