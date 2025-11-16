<?php

namespace App\Filament\Resources\Reports\Actions;

use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class UpholdReportAction
{
    public static function make(): Action
    {
        return Action::make('uphold')
            ->label('Uphold')
            ->icon('tabler-circle-check')
            ->color('danger')
            ->visible(
                fn (Report $record): bool => $record->status === 'pending' && Auth::user()->can('uphold', [$record])
            )
            ->requiresConfirmation()
            ->schema([
                Textarea::make('resolution_notes')
                    ->label('Resolution Notes')
                    ->placeholder('Explain why this report was upheld...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Report $record, array $data): void {
                \App\Actions\Reports\ResolveReport::run(
                    $record,
                    Auth::user(),
                    'upheld',
                    $data['resolution_notes']
                );

                Notification::make()
                    ->title('Report Upheld')
                    ->body('The report has been upheld successfully.')
                    ->success()
                    ->send();
            });
    }
}
