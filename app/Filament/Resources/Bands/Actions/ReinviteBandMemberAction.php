<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ReinviteBandMemberAction
{
    public static function make(Band $band): Action
    {
        return Action::make('reinvite_declined')
            ->label('Re-invite')
            ->color('primary')
            ->icon('tabler-mail')
            ->requiresConfirmation()
            ->modalHeading('Re-invite Member')
            ->modalDescription(fn($record) => "Send a new invitation to {$record->name}?")
            ->action(function ($record) use ($band): void {
                $user = User::find($record->user_id);

                try {
                    \App\Actions\Bands\InviteMember::run(
                        $band,
                        $user,
                        $record->role ?? 'member',
                        $record->position,
                        $record->name
                    );

                    Notification::make()
                        ->title('Invitation sent')
                        ->body("New invitation sent to {$record->name}")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to send invitation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->visible(
                fn($record): bool => $record->status === 'declined' &&
                    Auth::user()->can('update', $band)
            );
    }
}
