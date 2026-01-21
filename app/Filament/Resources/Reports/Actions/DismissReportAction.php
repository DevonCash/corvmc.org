<?php

namespace App\Filament\Resources\Reports\Actions;

use CorvMC\Moderation\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DismissReportAction
{
    public static function make(): Action
    {
        return Action::make('dismiss')
            ->label('Dismiss')
            ->icon('tabler-circle-x')
            ->color('success')
            ->visible(
                fn (Report $record): bool => $record->status === 'pending' && Auth::user()->can('dismiss', [$record])
            )
            ->requiresConfirmation()
            ->schema([
                Textarea::make('resolution_notes')
                    ->label('Resolution Notes')
                    ->placeholder('Explain why this report was dismissed...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Report $record, array $data): void {
                \App\Actions\Reports\ResolveReport::run(
                    $record,
                    Auth::user(),
                    'dismissed',
                    $data['resolution_notes']
                );

                Notification::make()
                    ->title('Report Dismissed')
                    ->body('The report has been dismissed successfully.')
                    ->success()
                    ->send();
            });
    }
}
