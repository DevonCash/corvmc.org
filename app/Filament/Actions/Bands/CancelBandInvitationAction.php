<?php

namespace App\Filament\Actions\Bands;

use App\Models\User;
use CorvMC\Membership\Services\BandService;
use CorvMC\Support\Models\Invitation;
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
            ->modalDescription(fn (?Model $record) => $record instanceof Invitation
                ? "Cancel the invitation sent to {$record->user->name}?"
                : 'Cancel this invitation?')
            ->visible(function (?Model $record) {
                if (! $record instanceof Invitation || ! $record->isPending()) {
                    return false;
                }

                $user = auth()->user();
                $band = $record->invitable;

                return $user && ($band->isOwner($user) || $band->isAdmin($user));
            })
            ->authorize(fn (?Model $record) => auth()->user()?->can('retract', $record))
            ->action(function (Model $record) {
                $userName = $record->user->name;
                app(BandService::class)->retractInvitation($record);

                Notification::make()
                    ->title('Invitation cancelled')
                    ->body("The invitation to {$userName} has been cancelled.")
                    ->success()
                    ->send();
            });
    }
}
