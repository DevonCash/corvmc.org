<?php

namespace App\Filament\Actions\Bands;

use App\Models\User;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Services\BandService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CancelBandInvitationAction
{
    public static function make(): Action
    {
        return Action::make('cancel_invitation')
            ->label('Cancel Invite')
            ->icon('tabler-mail-off')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Cancel Invitation')
            ->modalDescription(fn (?Model $record) => $record instanceof BandMember
                ? "Cancel the invitation sent to {$record->user->name}?"
                : 'Cancel this invitation?')
            ->visible(function (?Model $record) {
                if (! $record instanceof BandMember || $record->status !== 'invited') {
                    return false;
                }

                $user = User::me();

                return $user && ($record->band->isOwner($user) || $record->band->isAdmin($user));
            })
            ->authorize(fn (?Model $record) => auth()->user()?->can('cancel', $record))
            ->action(function (Model $record) {
                $userName = $record->user->name;
                app(BandService::class)->cancelInvitation($record);

                Notification::make()
                    ->title('Invitation cancelled')
                    ->body("The invitation to {$userName} has been cancelled.")
                    ->success()
                    ->send();
            });
    }
}
