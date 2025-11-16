<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

class MarkReturnedToOwnerAction
{
    public static function make(): Action
    {
        return Action::make('mark_returned_to_owner')
            ->label('Return to Owner')
            ->icon('tabler-arrow-back-up')
            ->color('danger')
            ->modalHeading('Return Equipment to Owner')
            ->modalDescription(
                fn ($record) => "Mark '{$record->name}' as returned to its original owner. ".
                    'This will retire the equipment from the lending library.'
            )
            ->action(function ($record) {
                if ($record->is_checked_out) {
                    throw new \Exception('Cannot return equipment to owner while it is checked out to a member.');
                }

                \App\Actions\Equipment\MarkReturnedToOwner::run($record);

                Notification::make()
                    ->title('Equipment Returned to Owner')
                    ->body("Successfully marked {$record->name} as returned to owner")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-arrow-back-up')
            ->visible(
                fn ($record) => $record->isOnLoanToCmc() &&
                    ! $record->is_checked_out &&
                    $record->ownership_status === 'on_loan_to_cmc'
            );
    }
}
