<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Widgets;

use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class UpcomingClosuresWidget extends Widget
{
    protected string $view = 'filament.staff.widgets.upcoming-closures';

    protected int|string|array $columnSpan = 'full';

    public function getClosures(): Collection
    {
        return SpaceClosure::query()
            ->where('starts_at', '<=', now()->endOfWeek())
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    public function getClosureUrl(SpaceClosure $closure): string
    {
        return SpaceClosureResource::getUrl('edit', ['record' => $closure]);
    }

    public static function canView(): bool
    {
        return SpaceClosure::query()
            ->where('starts_at', '<=', now()->endOfWeek())
            ->where('ends_at', '>', now())
            ->exists();
    }
}
