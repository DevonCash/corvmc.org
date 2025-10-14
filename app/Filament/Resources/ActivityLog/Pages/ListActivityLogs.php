<?php

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Resources\ActivityLog\Widgets\ActivityStatsWidget;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cleanup')
                ->label('Cleanup Old Logs')
                ->icon('tabler-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cleanup Old Activity Logs')
                ->modalDescription('This will delete activity logs older than 90 days. This action cannot be undone.')
                ->modalSubmitActionLabel('Delete Old Logs')
                ->action(function () {
                    $deleted = static::getResource()::getModel()::where('created_at', '<', now()->subDays(90))->delete();

                    $this->notification()
                        ->title('Cleanup Complete')
                        ->body("Deleted {$deleted} old activity logs")
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => User::me()?->can('delete activity log') ?? false),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityStatsWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Community Activity';
    }

    public function getSubheading(): ?string
    {
        return 'Track community engagement and system changes';
    }
}
