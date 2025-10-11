<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AcceptBandInvitationAction
{
    public static function make(Band $band): Action
    {
        return Action::make('accept_invitation')
            ->label('Accept')
            ->color('success')
            ->icon('tabler-check')
            ->requiresConfirmation()
            ->modalHeading('Accept Band Invitation')
            ->modalDescription(fn($record) => "Accept invitation to join {$band->name}?")
            ->action(function ($record) use ($band): void {
                $user = User::find($record->user_id);

                try {
                    \App\Actions\Bands\AcceptInvitation::run($band, $user);

                    Notification::make()
                        ->title('Invitation accepted')
                        ->body("Welcome to {$band->name}!")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to accept invitation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(
                fn($record): bool => $record->status === 'invited' &&
                    $record->user_id === Auth::user()->id
            );
    }
}
