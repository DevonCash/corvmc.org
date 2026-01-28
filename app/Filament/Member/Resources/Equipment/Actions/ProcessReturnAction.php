<?php

namespace App\Filament\Member\Resources\Equipment\Actions;

use CorvMC\Equipment\Actions\ProcessReturn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class ProcessReturnAction
{
    public static function make(): Action
    {
        return Action::make('process_return')
            ->label('Process Return')
            ->icon('tabler-receipt-refund')
            ->color('primary')
            ->modalWidth('md')
            ->modalHeading('Process Equipment Return')
            ->modalDescription('Record the return of this equipment')
            ->schema([
                Select::make('condition_in')
                    ->label('Condition When Returned')
                    ->options([
                        'excellent' => 'Excellent',
                        'good' => 'Good',
                        'fair' => 'Fair',
                        'poor' => 'Poor',
                        'needs_repair' => 'Needs Repair',
                    ])
                    ->required()
                    ->reactive(),

                Textarea::make('damage_notes')
                    ->label('Damage/Condition Notes')
                    ->placeholder('Describe any damage or condition changes')
                    ->rows(3)
                    ->visible(
                        fn (callable $get) => in_array($get('condition_in'), ['fair', 'poor', 'needs_repair'])
                    ),
            ])
            ->action(function (array $data, $record) {
                $currentLoan = $record->currentLoan;

                if (! $currentLoan) {
                    throw new \Exception('No active loan found for this equipment.');
                }

                $loan = ProcessReturn::run(
                    loan: $currentLoan,
                    conditionIn: $data['condition_in'],
                    damageNotes: $data['damage_notes'] ?? null
                );

                // Update equipment condition if it changed
                if ($data['condition_in'] !== $record->condition) {
                    $record->update(['condition' => $data['condition_in']]);
                }

                Notification::make()
                    ->title('Equipment Returned')
                    ->body("Successfully processed return of {$record->name}")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalIcon('tabler-receipt-refund')
            ->visible(fn ($record) => $record->is_checked_out && $record->currentLoan);
    }
}
