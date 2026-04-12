<?php

namespace App\Filament\Actions\Equipment;

use CorvMC\Equipment\Data\ReturnData;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Equipment\Services\EquipmentService;
use CorvMC\Equipment\States\EquipmentLoan\CheckedOut;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Filament Action for returning equipment from a loan.
 * 
 * This action handles the UI concerns for equipment returns
 * and delegates business logic to the EquipmentService.
 */
class EquipmentReturnAction
{
    public static function make(): Action
    {
        return Action::make('return_equipment')
            ->label('Return')
            ->icon('tabler-package-import')
            ->color('success')
            ->modalWidth('md')
            ->modalHeading('Return Equipment')
            ->modalDescription('Record the return of this equipment')
            ->schema([
                Select::make('condition_in')
                    ->label('Condition When Returned')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                    ])
                    ->default('good')
                    ->required(),

                Textarea::make('return_notes')
                    ->label('Return Notes')
                    ->placeholder('Note any damage, issues, or other observations')
                    ->rows(3),
            ])
            ->action(function (array $data, EquipmentLoan $record) {
                // Create DTO from form data
                $returnData = ReturnData::from([
                    'loan' => $record,
                    'conditionIn' => $data['condition_in'],
                    'returnNotes' => $data['return_notes'] ?? null,
                ]);

                // Use service to perform return
                $service = app(EquipmentService::class);
                $loan = $service->return($returnData);

                Notification::make()
                    ->title('Equipment Returned')
                    ->body("Successfully returned {$loan->equipment->name}")
                    ->success()
                    ->send();

                return $loan;
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-package-import')
            ->visible(fn ($record) => $record instanceof EquipmentLoan && $record->state === CheckedOut::class);
    }
}