<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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
                ->visible(fn (): bool => auth()->user()?->can('delete activity log') ?? false),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityLogResource\Widgets\ActivityStatsWidget::class,
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
