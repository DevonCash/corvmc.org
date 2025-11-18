<?php

namespace App\Actions\ActivityLogs;

use Filament\Actions\Action;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Activitylog\Models\Activity;

class CleanupLogs
{
    use AsAction;

    public function handle(): void
    {
        Activity::where('created_at', '<', now()->subDays(90))->delete();
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
                static::run();

                \Filament\Notifications\Notification::make()
                    ->title('Logs cleaned up')
                    ->success()
                    ->send();
            });
    }
}
