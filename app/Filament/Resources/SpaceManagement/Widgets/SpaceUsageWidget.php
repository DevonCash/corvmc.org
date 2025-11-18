<?php

namespace App\Filament\Resources\SpaceManagement\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Filament\Widgets\Widget;

class SpaceUsageWidget extends Widget
{
    protected string $view = 'filament.resources.space-management.widgets.space-usage-widget';

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
            ->get()
            ->map(function ($r) {
                return [
                    'type' => $r->getReservationTypeLabel(),
                    'type_class' => get_class($r),
                    'model' => $r,
                    'start' => $r->reserved_at,
                    'end' => $r->reserved_until,
                    'title' => $r->getDisplayTitle(),
                    'duration' => $r->duration,
                    'status' => $r->status,
                    'payment_status' => $r instanceof \App\Models\RehearsalReservation ? $r->payment_status : null,
                    'cost' => $r instanceof \App\Models\RehearsalReservation ? $r->cost : null,
                ];
            });
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
