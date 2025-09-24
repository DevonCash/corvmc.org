<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Services\EquipmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class MarkOverdueAction
{
    public static function make(): Action
    {
        return Action::make('mark_overdue')
            ->label('Mark Overdue')
            ->icon('tabler-alert-triangle')
            ->color('warning')
            ->modalHeading('Mark Equipment as Overdue')
            ->modalDescription(fn ($record) =>
                "Mark the current loan of '{$record->name}' as overdue. This will send notifications to the borrower."
            )
            ->action(function ($record) {
                try {
                    $equipmentService = app(EquipmentService::class);
                    $currentLoan = $record->currentLoan;

                    if (!$currentLoan) {
                        throw new \Exception('No active loan found for this equipment.');
                    }

                    $equipmentService->markOverdue($currentLoan);

                    Notification::make()
                        ->title('Loan Marked Overdue')
                        ->body("Successfully marked {$record->name} loan as overdue")
                        ->warning()
                        ->send();

                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Failed to Mark Overdue')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-alert-triangle')
            ->visible(fn ($record) =>
                $record->is_checked_out &&
                $record->currentLoan &&
                $record->currentLoan->due_at->isPast() &&
                $record->currentLoan->status !== 'overdue'
            );
    }
}
