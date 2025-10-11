<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DeclineBandInvitationAction
{
    public static function make(Band $band): Action
    {
        return Action::make('decline_invitation')
            ->label('Decline')
            ->color('danger')
            ->icon('tabler-x')
            ->requiresConfirmation()
            ->modalHeading('Decline Band Invitation')
            ->modalDescription(fn($record) => "Decline invitation to join {$band->name}?")
            ->action(function ($record) use ($band): void {
                $user = User::find($record->user_id);

                try {
                    \App\Actions\Bands\DeclineInvitation::run($band, $user);

                    Notification::make()
                        ->title('Invitation declined')
                        ->body('You have declined the invitation')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to decline invitation')
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
