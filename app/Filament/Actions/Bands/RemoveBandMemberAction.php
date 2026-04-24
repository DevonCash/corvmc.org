<?php

namespace App\Filament\Actions\Bands;

use App\Models\User;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Services\BandService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class RemoveBandMemberAction
{
    public static function make(): Action
    {
        return Action::make('remove_member')
            ->label('Remove')
            ->icon('tabler-user-minus')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (?Model $record) => $record instanceof BandMember
                ? "Remove {$record->user->name}"
                : 'Remove Member')
            ->modalDescription(fn (?Model $record) => $record instanceof BandMember
                ? "Are you sure you want to remove {$record->user->name} from {$record->band->name}?"
                : 'Are you sure you want to remove this member?')
            ->visible(function (?Model $record) {
                if (! $record instanceof BandMember || $record->status !== 'active') {
                    return false;
                }

                // Can't remove the owner; use CancelBandInvitationAction for invited members
                if ($record->role === 'owner') {
                    return false;
                }

                $user = User::me();

                return $user && ($record->band->isOwner($user) || $record->band->isAdmin($user));
            })
            ->authorize(fn (?Model $record) => auth()->user()?->can('delete', $record))
            ->action(function (Model $record) {
                $userName = $record->user->name;
                app(BandService::class)->removeMember($record->band, $record->user);

                Notification::make()
                    ->title('Member removed')
                    ->body("{$userName} has been removed from the band.")
                    ->success()
                    ->send();
            });
    }
}
