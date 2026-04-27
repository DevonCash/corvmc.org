<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class VolunteerPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-heart-handshake';

    protected string $view = 'filament.pages.volunteer';

    protected static ?string $title = 'Volunteering';

    protected static ?string $slug = 'volunteering';

    protected static ?int $navigationSort = 5;

    protected ?Collection $cachedOpenShifts = null;

    protected ?Collection $cachedMyShifts = null;

    protected ?Collection $cachedHistory = null;

    protected static bool $showInNavigation = false;

    protected function resetCache(): void
    {
        $this->cachedOpenShifts = null;
        $this->cachedMyShifts = null;
        $this->cachedHistory = null;
    }

    public static function canAccess(): bool
    {
        // return false; // Temporarily disable access until the feature is ready
        return auth()->user()?->can('volunteer.signup') ?? false;
    }

    /**
     * Upcoming shifts with available capacity, grouped by event.
     * Includes the current user's existing sign-up status per shift.
     */
    public function getOpenShifts(): Collection
    {
        if ($this->cachedOpenShifts !== null) {
            return $this->cachedOpenShifts;
        }

        $user = User::me();

        $shifts = Shift::query()
            ->upcoming()
            ->withAvailableCapacity()
            ->with(['position', 'event'])
            ->withCount(['hourLogs as active_count' => fn($q) => $q->active()])
            ->orderBy('start_at')
            ->get();

        // Get user's active hour logs for these shifts
        $myLogsByShift = HourLog::where('user_id', $user->id)
            ->whereIn('shift_id', $shifts->pluck('id'))
            ->active()
            ->get()
            ->keyBy('shift_id');

        return $this->cachedOpenShifts = $shifts->map(function (Shift $shift) use ($myLogsByShift) {
            $myLog = $myLogsByShift->get($shift->id);

            return [
                'shift' => $shift,
                'my_hour_log' => $myLog,
                'can_sign_up' => $myLog === null,
                'available' => $shift->capacity - $shift->active_count,
            ];
        })->groupBy(fn($item) => $item['shift']->event?->title ?? 'Standalone Shifts');
    }

    /**
     * Current user's upcoming confirmed/checked-in shifts.
     */
    public function getMyUpcomingShifts(): Collection
    {
        if ($this->cachedMyShifts !== null) {
            return $this->cachedMyShifts;
        }

        $user = User::me();

        return $this->cachedMyShifts = HourLog::where('user_id', $user->id)
            ->whereNotNull('shift_id')
            ->whereIn('status', [Confirmed::getMorphClass(), CheckedIn::getMorphClass()])
            ->whereHas('shift', fn($q) => $q->where('end_at', '>', now()))
            ->with(['shift.position', 'shift.event'])
            ->get()
            ->sortBy('shift.start_at')
            ->values();
    }

    /**
     * Current user's past volunteer history (most recent 20).
     */
    public function getMyHistory(): Collection
    {
        if ($this->cachedHistory !== null) {
            return $this->cachedHistory;
        }

        $user = User::me();

        return $this->cachedHistory = HourLog::where('user_id', $user->id)
            ->with(['shift.position', 'shift.event', 'position'])
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Sign up for a shift.
     */
    public function signUp(int $shiftId): void
    {
        $user = User::me();
        $shift = Shift::findOrFail($shiftId);

        try {
            app(HourLogService::class)->signUp($user, $shift);

            $this->resetCache();

            Notification::make()
                ->title('Signed up!')
                ->body("You've signed up for {$shift->position->title}.")
                ->success()
                ->send();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            Notification::make()
                ->title('Could not sign up')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Exception $e) {
            report($e);

            Notification::make()
                ->title('Could not sign up')
                ->body('Something went wrong. Please try again.')
                ->danger()
                ->send();
        }
    }

    /**
     * Self-check-in for a confirmed shift.
     */
    public function checkIn(int $hourLogId): void
    {
        $user = User::me();
        $hourLog = HourLog::where('id', $hourLogId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! $hourLog->status instanceof Confirmed) {
            Notification::make()
                ->title('Could not check in')
                ->body('This shift is not in a confirmed state.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(HourLogService::class)->checkIn($hourLog);

            $this->resetCache();

            Notification::make()
                ->title('Checked in!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Could not check in')
                ->body('Something went wrong. Please try again.')
                ->danger()
                ->send();
        }
    }

    /**
     * Self-check-out from a checked-in shift.
     */
    public function checkOut(int $hourLogId): void
    {
        $user = User::me();
        $hourLog = HourLog::where('id', $hourLogId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (! $hourLog->status instanceof CheckedIn) {
            Notification::make()
                ->title('Could not check out')
                ->body('You are not currently checked in to this shift.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(HourLogService::class)->checkOut($hourLog);

            $this->resetCache();

            Notification::make()
                ->title('Checked out — thanks for volunteering!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Could not check out')
                ->body('Something went wrong. Please try again.')
                ->danger()
                ->send();
        }
    }

    /**
     * Whether a shift is within the check-in window:
     * 30 minutes before start through shift end.
     */
    public static function isInCheckInWindow(Shift $shift): bool
    {
        $now = now();

        return $now->greaterThanOrEqualTo($shift->start_at->copy()->subMinutes(30))
            && $now->lessThanOrEqualTo($shift->end_at);
    }
}
