<?php

namespace App\Filament\Staff\Pages;

use BackedEnum;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Events\Models\Event;
use CorvMC\Finance\Actions\Subscriptions\GetSubscriptionStats;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Pages\Page;
use Filament\Panel;
use Spatie\Activitylog\Models\Activity;

class StaffDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.staff-dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Home';

    protected static ?int $navigationSort = -100;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    /**
     * Get today's operations data including space status, reservations, tonight's event, and equipment loans.
     */
    public function getTodaysOperationsData(): array
    {
        $today = today();

        // Check if space is closed today
        $closure = SpaceClosure::onDate($today)->first();
        $spaceStatus = $closure ? [
            'is_open' => false,
            'reason' => $closure->reason ?? $closure->type->getLabel(),
        ] : [
            'is_open' => true,
            'reason' => null,
        ];

        // Get today's reservations
        $reservations = RehearsalReservation::query()
            ->whereDate('reserved_at', $today)
            ->with(['reservable', 'charge'])
            ->orderBy('reserved_at')
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'title' => $reservation->getDisplayTitle(),
                    'start_time' => $reservation->reserved_at->format('g:i A'),
                    'end_time' => $reservation->reserved_until->format('g:i A'),
                    'duration' => $reservation->hours_used,
                    'status' => $reservation->status,
                    'is_paid' => $reservation->charge?->status->isPaid() ?? true,
                    'amount' => $reservation->charge?->net_amount?->formatTo('en_US'),
                ];
            });

        // Calculate stats
        $totalHours = $reservations->sum('duration');
        $totalRevenue = RehearsalReservation::query()
            ->whereDate('reserved_at', $today)
            ->whereHas('charge', fn ($q) => $q->where('status', 'paid'))
            ->with('charge')
            ->get()
            ->sum(fn ($r) => $r->charge?->net_amount?->getMinorAmount()->toInt() ?? 0);
        $unpaidCount = $reservations->filter(fn ($r) => ! $r['is_paid'] && $r['amount'])->count();

        // Get tonight's event
        $tonightsEvent = Event::publishedToday()
            ->with(['venue', 'organizer'])
            ->first();

        // Get equipment currently checked out
        $checkedOutEquipment = EquipmentLoan::checkedOut()
            ->with(['equipment', 'borrower'])
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'equipment_name' => $loan->equipment->name,
                    'borrower_name' => $loan->borrower->name,
                    'due_at' => $loan->due_at,
                    'is_overdue' => $loan->is_overdue,
                    'days_overdue' => $loan->days_overdue,
                ];
            });

        return [
            'space_status' => $spaceStatus,
            'reservations' => $reservations,
            'stats' => [
                'total_hours' => $totalHours,
                'total_revenue' => \Brick\Money\Money::ofMinor($totalRevenue, 'USD')->formatTo('en_US'),
                'unpaid_count' => $unpaidCount,
            ],
            'tonights_event' => $tonightsEvent,
            'checked_out_equipment' => $checkedOutEquipment,
        ];
    }

    /**
     * Get membership health data using GetSubscriptionStats action.
     */
    public function getMembershipHealthData(): array
    {
        $stats = GetSubscriptionStats::run();

        // Estimate Stripe fees: 2.9% + $0.30 per transaction
        $subscriptionCount = $stats->active_subscriptions_count;
        $mrrTotalCents = $stats->mrr_total->getMinorAmount()->toInt();

        // Per-transaction fee ($0.30 × number of subscriptions)
        $fixedFeesCents = $subscriptionCount * 30;
        // Percentage fee (2.9% of total)
        $percentageFeesCents = (int) round($mrrTotalCents * 0.029);
        $totalFeesCents = $fixedFeesCents + $percentageFeesCents;

        $feeCost = \Brick\Money\Money::ofMinor($totalFeesCents, 'USD');
        $netRevenue = $stats->mrr_total->minus($feeCost);

        return [
            'sustaining_members' => $stats->sustaining_members,
            'subscription_net_change' => $stats->subscription_net_change_last_month,
            'mrr_total' => $stats->mrr_total->formatTo('en_US'),
            'mrr_base' => $netRevenue->formatTo('en_US'),
            'fee_cost' => $feeCost->formatTo('en_US'),
            'average_mrr' => $stats->average_mrr->formatTo('en_US'),
            'median_contribution' => $stats->median_contribution->formatTo('en_US'),
            'new_members_this_month' => $stats->new_members_this_month,
            'active_subscriptions' => $stats->active_subscriptions_count,
        ];
    }

    /**
     * Get recent activities for the activity feed.
     */
    public function getRecentActivities(): \Illuminate\Support\Collection
    {
        // Filter out activities with subject types that no longer exist
        return Activity::with(['causer'])
            ->whereNotNull('subject_type')
            ->where(function ($query) {
                $query->where('subject_type', 'like', 'CorvMC\\%')
                    ->orWhere('subject_type', 'like', 'App\\Models\\User%')
                    ->orWhere('subject_type', 'like', 'App\\Models\\Band%')
                    ->orWhere('subject_type', 'like', 'App\\Models\\MemberProfile%');
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (Activity $activity) {
                // Safely load subject - skip if class doesn't exist
                try {
                    $activity->load('subject');
                } catch (\Throwable) {
                    return null;
                }

                if ($activity->subject === null) {
                    return null;
                }

                return [
                    'id' => $activity->id,
                    'description' => $this->formatActivityDescription($activity),
                    'causer_name' => $activity->causer?->name ?? 'System',
                    'created_at' => $activity->created_at,
                    'icon' => $this->getActivityIcon($activity),
                    'color' => $this->getActivityColor($activity),
                ];
            })
            ->filter()
            ->take(15);
    }

    protected function formatActivityDescription(Activity $activity): string
    {
        $causerName = $activity->causer?->name ?? 'System';
        $subject = $activity->subject;

        return match ($activity->description) {
            'User account created' => "{$causerName} joined the community",
            'User account updated' => "{$causerName} updated their account",
            'Production created' => $this->formatWithSubject($causerName, 'created event', $subject?->title),
            'Production updated' => $this->formatWithSubject($causerName, 'updated event', $subject?->title),
            'Production deleted' => $this->formatWithSubject($causerName, 'removed event', $subject?->title),
            'Band profile created' => $this->formatWithSubject($causerName, 'created band', $subject?->name),
            'Band profile updated' => $this->formatWithSubject($causerName, 'updated band', $subject?->name),
            'Band profile deleted' => $this->formatWithSubject($causerName, 'removed band', $subject?->name),
            'Member profile created' => "{$causerName} completed their profile",
            'Member profile updated' => "{$causerName} updated their profile",
            'Practice space reservation created' => $this->formatReservationDescription($causerName, 'booked', $subject),
            'Practice space reservation updated' => $this->formatReservationDescription($causerName, 'updated reservation for', $subject),
            'Practice space reservation deleted' => $this->formatReservationDescription($causerName, 'cancelled reservation for', $subject),
            'Reservation has been created' => $this->formatReservationDescription($causerName, 'booked', $subject),
            'Reservation has been updated' => $this->formatReservationDescription($causerName, 'updated reservation for', $subject),
            'Reservation has been deleted' => $this->formatReservationDescription($causerName, 'cancelled reservation for', $subject),
            'Equipment loan created' => $this->formatWithSubject($causerName, 'requested', $subject?->equipment?->name),
            'Equipment loan updated' => $this->formatWithSubject($causerName, 'updated loan for', $subject?->equipment?->name),
            default => $activity->description ?: "{$causerName} performed an action",
        };
    }

    protected function formatWithSubject(string $causerName, string $action, ?string $subjectName): string
    {
        if ($subjectName) {
            return "{$causerName} {$action} \"{$subjectName}\"";
        }

        return "{$causerName} {$action}";
    }

    protected function formatReservationDescription(string $causerName, string $action, $reservation): string
    {
        if ($reservation && $reservation->reserved_at) {
            $date = $reservation->reserved_at->format('M j');
            $time = $reservation->reserved_at->format('g:i A');

            return "{$causerName} {$action} {$date} at {$time}";
        }

        return "{$causerName} {$action} practice space";
    }

    protected function getActivityIcon(Activity $activity): string
    {
        return match ($activity->description) {
            'User account created' => 'tabler-user-plus',
            'User account updated' => 'tabler-user-edit',
            'Production created' => 'tabler-calendar-plus',
            'Production updated' => 'tabler-calendar-up',
            'Production deleted' => 'tabler-calendar-minus',
            'Band profile created' => 'tabler-users-plus',
            'Band profile updated' => 'tabler-users',
            'Band profile deleted' => 'tabler-users-minus',
            'Member profile created' => 'tabler-user-check',
            'Member profile updated' => 'tabler-user-edit',
            'Practice space reservation created',
            'Reservation has been created' => 'tabler-home-plus',
            'Practice space reservation updated',
            'Reservation has been updated' => 'tabler-home-edit',
            'Practice space reservation deleted',
            'Reservation has been deleted' => 'tabler-home-minus',
            'Equipment loan created' => 'tabler-tool',
            'Equipment loan updated' => 'tabler-tool',
            default => 'tabler-activity',
        };
    }

    protected function getActivityColor(Activity $activity): string
    {
        return match (true) {
            str_contains($activity->description ?? '', 'created') => 'success',
            str_contains($activity->description ?? '', 'updated') => 'info',
            str_contains($activity->description ?? '', 'deleted') => 'danger',
            default => 'gray',
        };
    }
}
