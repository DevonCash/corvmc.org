<?php

namespace App\Filament\Resources\SpaceManagement\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Filament\Widgets\Widget;

class SpaceUsageWidget extends Widget
{
    protected string $view = 'filament.resources.space-management.widgets.space-usage-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getNextSpaceUsage(): ?Reservation
    {
        // Get next reservation of any type
        return Reservation::with('reservable')
            ->where('reserved_at', '>', now())
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->orderBy('reserved_at', 'asc')
            ->first();
    }

    public function getTodaysSpaceUsage(): \Illuminate\Support\Collection
    {
        return Reservation::with('reservable')
            ->whereDate('reserved_at', today())
            ->where('status', '!=', ReservationStatus::Cancelled->value)
            ->orderBy('reserved_at', 'asc')
            ->get();
    }

    public function getViewData(): array
    {
        $next = $this->getNextSpaceUsage();
        $today = $this->getTodaysSpaceUsage();

        $todayRehearsals = $today->where('type_class', \App\Models\RehearsalReservation::class);
        $todayProductions = $today->where('type_class', \App\Models\EventReservation::class);

        return [
            'nextItem' => $next,
            'nextType' => $next ? $next->getReservationTypeLabel() : null,
            'todaysUsage' => $today,
            'todaysCount' => $today->count(),
            'hoursToday' => $today->sum('duration'),
            'revenueToday' => $todayRehearsals
                ->filter(fn ($r) => $r['cost'] !== null && $r['payment_status'] === PaymentStatus::Paid)
                ->sum(fn ($r) => $r['cost']->getMinorAmount()->toInt()) / 100,
            'rehearsalCount' => $todayRehearsals->count(),
            'productionCount' => $todayProductions->count(),
        ];
    }
}
