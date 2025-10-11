<?php

namespace App\Filament\Resources\Reports\Actions;

use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EscalateReportAction
{
    public static function make(): Action
    {
        return Action::make('escalate')
            ->label('Escalate')
            ->icon('tabler-circle-arrow-up')
            ->color('warning')
            ->visible(
                fn(Report $record): bool =>
                $record->status === 'pending' && Auth::user()->can('escalate', [$record])
            )
            ->requiresConfirmation()
            ->schema([
                Textarea::make('resolution_notes')
                    ->label('Escalation Notes')
                    ->placeholder('Explain why this report needs admin review...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Report $record, array $data): void {
                \App\Actions\Reports\ResolveReport::run(
                    $record,
                    Auth::user(),
                    'escalated',
                    $data['resolution_notes']
                );

                Notification::make()
                    ->title('Report Escalated')
                    ->body('The report has been escalated to admin review.')
                    ->warning()
                    ->send();
            });
    }
}
