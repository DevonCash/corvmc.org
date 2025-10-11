<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CancelBandInvitationAction
{
    public static function make(Band $band): Action
    {
        return Action::make('cancel_invitation')
            ->label('Cancel Invitation')
            ->color('danger')
            ->icon('tabler-x')
            ->requiresConfirmation()
            ->modalHeading('Cancel Band Invitation')
            ->modalDescription(fn($record) => "Cancel the invitation for {$record->name}?")
            ->action(function ($record) use ($band): void {
                $user = User::find($record->user_id);

                try {
                    \App\Actions\Bands\CancelInvitation::run($band, $user);

                    Notification::make()
                        ->title('Invitation cancelled')
                        ->body("Invitation for {$record->name} has been cancelled")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to cancel invitation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(
                fn($record): bool => $record->status === 'invited' &&
                    Auth::user()->can('update', $band)
            );
    }
}
