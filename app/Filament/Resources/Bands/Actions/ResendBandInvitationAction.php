<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ResendBandInvitationAction
{
    public static function make(Band $band): Action
    {
        return Action::make('resend_invitation')
            ->label('Resend')
            ->color('warning')
            ->icon('tabler-mail-forward')
            ->action(function ($record) use ($band): void {
                $user = User::find($record->user_id);

                \App\Actions\Bands\ResendInvitation::run($band, $user);

                Notification::make()
                    ->title('Invitation resent')
                    ->body("Invitation resent to {$record->name}")
                    ->success()
                    ->send();
            })
            ->visible(
                fn($record): bool => $record->status === 'invited' &&
                    Auth::user()->can('manageMembers', $band)
            );
    }
}
