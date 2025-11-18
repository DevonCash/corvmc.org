<?php

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Actions\ActivityLogs\CleanupLogs;
use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Resources\ActivityLog\Widgets\ActivityStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CleanupLogs::filamentAction()
                ->authorize('delete'),
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
