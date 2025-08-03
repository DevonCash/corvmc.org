<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class WeeklyOverviewWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Weekly Practice Space Usage';
    }

    public function getDescription(): ?string
    {
        return 'Practice hours by day for '.Carbon::now()->startOfWeek()->format('M j').' - '.Carbon::now()->endOfWeek()->format('M j');
    }

    protected function getData(): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Get reservations for this week
        $reservations = Reservation::with('user')
            ->whereBetween('reserved_at', [$startOfWeek, $endOfWeek])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy(function ($reservation) {
                return $reservation->reserved_at->format('Y-m-d');
            });

        $user = User::me();
        $dailyData = [];
        $personalData = [];
        $labels = [];

        // Generate data for each day of the week
        for ($date = $startOfWeek->copy(); $date <= $endOfWeek; $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $dayReservations = $reservations->get($dateString, collect());

            $totalHours = $dayReservations->sum('hours_used');
            $personalHours = $dayReservations->where('user_id', $user->id)->sum('hours_used');

            $dailyData[] = $totalHours;
            $personalData[] = $personalHours;
            $labels[] = $date->format('D, M j');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Hours',
                    'data' => $dailyData,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#1d4ed8',
                    'borderWidth' => 2,
                    'fill' => false,
                ],
                [
                    'label' => 'Your Hours',
                    'data' => $personalData,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 2,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Hours',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Day',
                    ],
                ],
            ],
            'elements' => [
                'point' => [
                    'radius' => 4,
                    'hoverRadius' => 6,
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}
