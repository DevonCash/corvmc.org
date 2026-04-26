<?php

namespace App\Filament\Actions\Bands;

use CorvMC\Membership\Services\BandService;
use CorvMC\Support\Models\Invitation;
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
            ->modalDescription(fn (?Model $record) => $record instanceof Invitation
                ? "Join {$record->invitable->name} as a " . ($record->data['role'] ?? 'member') . '?'
                : 'Accept this band invitation?')
            ->visible(fn (?Model $record) => $record instanceof Invitation && $record->isPending())
            ->authorize(fn (?Model $record) => auth()->user()?->can('respond', $record))
            ->action(function (Model $record) {
                app(BandService::class)->acceptInvitation($record);

                Notification::make()
                    ->title('Invitation accepted')
                    ->body("You've joined {$record->invitable->name}.")
                    ->success()
                    ->send();
            });
    }
}
