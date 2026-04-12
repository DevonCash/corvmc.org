<?php

namespace App\Actions\ActivityLogs;

use App\Services\ActivityLogService;
use Filament\Actions\Action;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ActivityLogService::cleanup() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ActivityLogService directly.
 */
class CleanupLogs
{
    use AsAction;

    /**
     * @deprecated Use ActivityLogService::cleanup() instead
     */
    public function handle(): void
    {
        app(ActivityLogService::class)->cleanup();
    }

    public static function filamentAction(): Action
    {
        return Action::make('cleanup_logs')
            ->label('Clean Up Old Logs')
            ->icon('tabler-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This will permanently delete activity logs older than 90 days.')
            ->action(function () {
                $deleted = app(ActivityLogService::class)->cleanup();

                \Filament\Notifications\Notification::make()
                    ->title('Logs cleaned up')
                    ->body("Deleted {$deleted} old activity logs")
                    ->success()
                    ->send();
            });
    }
}
