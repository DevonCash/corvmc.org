<?php

namespace App\Filament\Staff\Resources\ActivityLog\Pages;

use App\Actions\ActivityLogs\CleanupLogs;
use App\Filament\Staff\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Staff\Resources\ActivityLog\Widgets\ActivityStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->with(['causer', 'subject']);
    }

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
