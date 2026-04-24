<?php

namespace App\Filament\Actions\ActivityLogs;

use App\Services\ActivityLogService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class CleanupLogsAction
{
    public static function make(): Action
    {
        return Action::make('cleanup_logs')
            ->label('Cleanup Logs')
            ->icon('tabler-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Clean Up Activity Logs')
            ->modalDescription('This will permanently delete activity logs older than the specified number of days.')
            ->modalWidth('md')
            ->schema([
                TextInput::make('days_to_keep')
                    ->label('Days to Keep')
                    ->numeric()
                    ->default(90)
                    ->minValue(7)
                    ->required()
                    ->helperText('Logs older than this many days will be permanently deleted.'),
            ])
            ->action(function (array $data) {
                $service = app(ActivityLogService::class);
                $deleted = $service->cleanup((int) $data['days_to_keep']);

                Notification::make()
                    ->title('Logs cleaned up')
                    ->body("{$deleted} log entries were deleted.")
                    ->success()
                    ->send();
            });
    }
}
