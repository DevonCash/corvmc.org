<?php

namespace App\Filament\Actions\Bands;

use CorvMC\Membership\Services\BandService;
use CorvMC\Support\Models\Invitation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class DeclineBandInvitationAction
{
    public static function make(): Action
    {
        return Action::make('decline_invitation')
            ->label('Decline')
            ->icon('tabler-x')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Decline Band Invitation')
            ->modalDescription(fn (?Model $record) => $record instanceof Invitation
                ? "Decline the invitation to join {$record->invitable->name}?"
                : 'Decline this band invitation?')
            ->visible(fn (?Model $record) => $record instanceof Invitation && $record->isPending())
            ->authorize(fn (?Model $record) => auth()->user()?->can('respond', $record))
            ->action(function (Model $record) {
                $bandName = $record->invitable->name;
                app(BandService::class)->declineInvitation($record);

                Notification::make()
                    ->title('Invitation declined')
                    ->body("You've declined the invitation to {$bandName}.")
                    ->success()
                    ->send();
            });
    }
}
