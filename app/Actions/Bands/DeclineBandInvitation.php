<?php

namespace App\Actions\Bands;

use App\Concerns\AsFilamentAction;
use App\Exceptions\BandException;
use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class DeclineBandInvitation
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Decline an invitation to join a band.
     */
    public function handle(Band $band, User $user): void
    {
        if (! $band->memberships()->invited()->where('user_id', $user->id)->exists()) {
            throw BandException::userNotInvited();
        }

        $band->members()->updateExistingPivot($user->id, [
            'status' => 'declined',
        ]);
    }

    public static function filamentAction(): Action
    {
        return Action::make('decline_invitation')
            ->label('Decline')
            ->color('danger')
            ->icon('tabler-x')
            ->requiresConfirmation()
            ->modalHeading('Decline Band Invitation')
            ->modalDescription(fn($record) => "Decline invitation to join {$record->band->name}?")
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
