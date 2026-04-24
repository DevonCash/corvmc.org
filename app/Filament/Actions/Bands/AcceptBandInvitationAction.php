<?php

namespace App\Filament\Actions\Bands;

use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Services\BandService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class AcceptBandInvitationAction
{
    public static function make(): Action
    {
        return Action::make('accept_invitation')
            ->label('Accept')
            ->icon('tabler-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Accept Band Invitation')
            ->modalDescription(fn (?Model $record) => $record instanceof BandMember
                ? "Join {$record->band->name} as a {$record->role}?"
                : 'Accept this band invitation?')
            ->visible(fn (?Model $record) => $record instanceof BandMember && $record->status === 'invited')
            ->authorize(fn (?Model $record) => auth()->user()?->can('accept', $record))
            ->action(function (Model $record) {
                app(BandService::class)->acceptInvitation($record);

                Notification::make()
                    ->title('Invitation accepted')
                    ->body("You've joined {$record->band->name}.")
                    ->success()
                    ->send();
            });
    }
}
