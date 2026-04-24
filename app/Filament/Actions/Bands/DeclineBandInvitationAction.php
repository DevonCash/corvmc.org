<?php

namespace App\Filament\Actions\Bands;

use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Services\BandService;
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
            ->modalDescription(fn (?Model $record) => $record instanceof BandMember
                ? "Decline the invitation to join {$record->band->name}?"
                : 'Decline this band invitation?')
            ->visible(fn (?Model $record) => $record instanceof BandMember && $record->status === 'invited')
            ->authorize(fn (?Model $record) => auth()->user()?->can('decline', $record))
            ->action(function (Model $record) {
                $bandName = $record->band->name;
                app(BandService::class)->declineInvitation($record);

                Notification::make()
                    ->title('Invitation declined')
                    ->body("You've declined the invitation to {$bandName}.")
                    ->success()
                    ->send();
            });
    }
}
