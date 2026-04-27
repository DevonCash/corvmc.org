<?php

namespace App\Filament\Staff\Pages;

use CorvMC\Volunteering\Models\HourLog;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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

    /**
     * Cached result set for the current render cycle.
     * Reset on each Livewire update since public properties may have changed.
     */
    protected ?Collection $cachedLogs = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('volunteer.hours.report') ?? false;
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfQuarter()->toDateString();
        $this->end_date = now()->endOfQuarter()->toDateString();
    }

    public function updatedStartDate(): void
    {
        $this->cachedLogs = null;
    }

    public function updatedEndDate(): void
    {
        $this->cachedLogs = null;
    }

    public function updatedTagFilter(): void
    {
        $this->cachedLogs = null;
    }

    /**
     * Parse a date string safely, returning null on failure.
     */
    protected function parseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value, config('app.timezone'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Load all matching hour logs once per render cycle.
     */
    protected function getLogs(): Collection
    {
        if ($this->cachedLogs !== null) {
            return $this->cachedLogs;
        }

        $start = $this->parseDate($this->start_date ?? '');
        $end = $this->parseDate($this->end_date ?? '');

        if (! $start || ! $end) {
            return $this->cachedLogs = collect();
        }

        // Swap if the user entered them backwards
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $query = HourLog::query()
            ->countable()
            ->whereBetween('started_at', [
                $start->startOfDay(),
                $end->endOfDay(),
            ])
            ->with(['user', 'shift.position', 'position']);

        if (! empty($this->tag_filter)) {
            $query->withAnyTags([$this->tag_filter]);
        }

        return $this->cachedLogs = $query->get();
    }

    /**
     * Summary statistics for the header widgets.
     */
    public function getStats(): array
    {
        $logs = $this->getLogs();

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
    public function getHoursByVolunteer(): Collection
    {
        return $this->getLogs()
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
    public function getHoursByPosition(): Collection
    {
        return $this->getLogs()
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
