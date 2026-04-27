<?php

namespace App\Filament\Staff\Pages;

use CorvMC\Volunteering\Models\HourLog;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class VolunteerReportPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-report-analytics';

    protected static ?string $navigationLabel = 'Volunteer Report';

    protected static ?string $title = 'Volunteer Report';

    protected static string|\UnitEnum|null $navigationGroup = 'Volunteering';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.volunteer-report';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $tag_filter = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('volunteer.hours.report') ?? false;
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfQuarter()->toDateString();
        $this->end_date = now()->endOfQuarter()->toDateString();
    }

    /**
     * Base query for countable hour logs within the date range and tag filter.
     */
    protected function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = HourLog::query()
            ->countable()
            ->whereBetween('started_at', [
                Carbon::parse($this->start_date, config('app.timezone'))->startOfDay(),
                Carbon::parse($this->end_date, config('app.timezone'))->endOfDay(),
            ]);

        if (! empty($this->tag_filter)) {
            $query->withAnyTags([$this->tag_filter]);
        }

        return $query;
    }

    /**
     * Summary statistics for the header widgets.
     */
    public function getStats(): array
    {
        $logs = $this->baseQuery()
            ->with(['shift.position', 'position'])
            ->get();

        $totalMinutes = $logs->sum(fn (HourLog $log) => $log->minutes ?? 0);
        $uniqueVolunteers = $logs->pluck('user_id')->unique()->count();
        $shiftsStaffed = $logs->whereNotNull('shift_id')->pluck('shift_id')->unique()->count();

        return [
            'total_hours' => round($totalMinutes / 60, 1),
            'unique_volunteers' => $uniqueVolunteers,
            'shifts_staffed' => $shiftsStaffed,
        ];
    }

    /**
     * Hours grouped by volunteer.
     */
    public function getHoursByVolunteer(): \Illuminate\Support\Collection
    {
        return $this->baseQuery()
            ->with('user')
            ->get()
            ->groupBy('user_id')
            ->map(function ($logs) {
                $user = $logs->first()->user;
                $totalMinutes = $logs->sum(fn (HourLog $log) => $log->minutes ?? 0);

                return [
                    'name' => $user->name,
                    'total_hours' => round($totalMinutes / 60, 1),
                    'sessions' => $logs->count(),
                ];
            })
            ->sortByDesc('total_hours')
            ->values();
    }

    /**
     * Hours grouped by position.
     */
    public function getHoursByPosition(): \Illuminate\Support\Collection
    {
        return $this->baseQuery()
            ->with(['shift.position', 'position'])
            ->get()
            ->groupBy(fn (HourLog $log) => $log->resolvePosition()?->id ?? 0)
            ->map(function ($logs) {
                $position = $logs->first()->resolvePosition();
                $totalMinutes = $logs->sum(fn (HourLog $log) => $log->minutes ?? 0);

                return [
                    'title' => $position?->title ?? 'Unknown',
                    'total_hours' => round($totalMinutes / 60, 1),
                    'volunteer_count' => $logs->pluck('user_id')->unique()->count(),
                ];
            })
            ->sortByDesc('total_hours')
            ->values();
    }
}
