<?php

namespace App\Filament\Staff\Pages;

use App\Facades\Analytics;
use BackedEnum;
use Brick\Money\Money;
use CorvMC\Equipment\Models\EquipmentLoan;
use CorvMC\Events\Models\Event;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Cleared;
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
            ->with(['reservable'])
            ->orderBy('reserved_at')
            ->get();

        // Calculate stats from Orders — find Orders referencing today's reservations
        $totalHours = $reservations->sum('duration');
        $totalRevenue = 0;
        $unpaidCount = 0;

        $reservationIds = $reservations->pluck('id');
        if ($reservationIds->isNotEmpty()) {
            $todaysOrders = Order::whereHas('lineItems', function ($q) use ($reservationIds) {
                $q->where('product_type', 'rehearsal_time')
                    ->whereIn('product_id', $reservationIds);
            })->with('transactions')->get();

            $totalRevenue = $todaysOrders->sum(fn ($o) => $o->paidAmount());
            $unpaidCount = $todaysOrders->filter(fn ($o) => $o->status instanceof Pending)->count();
        }

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
                'total_revenue' => Money::ofMinor($totalRevenue, 'USD')->formatTo('en_US'),
                'unpaid_count' => $unpaidCount,
            ],
            'tonights_event' => $tonightsEvent,
            'checked_out_equipment' => $checkedOutEquipment,
        ];
    }

    /**
     * Get combined monthly revenue data (subscriptions + orders).
     */
    public function getMonthlyRevenueData(): array
    {
        // Get subscription stats
        $stats = Analytics::getSubscriptionStats();

        // Estimate Stripe fees for subscriptions: 2.9% + $0.30 per transaction
        $subscriptionCount = $stats->active_subscriptions_count;
        $mrrTotalCents = $stats->mrr_total->getMinorAmount()->toInt();
        $subscriptionFeesCents = ($subscriptionCount * 30) + (int) round($mrrTotalCents * 0.029);

        // Get this month's order data (guard against missing tables during migration)
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $transactions = Transaction::where('type', 'payment')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();

        // Cleared transactions by currency
        $clearedTxns = $transactions->filter(fn ($t) => $t->status instanceof Cleared);
        $byCurrency = $clearedTxns->groupBy('currency')->map(function ($group, $currency) {
            return [
                'method' => $currency,
                'count' => $group->count(),
                'total' => (int) $group->sum('amount'),
            ];
        });

        $ordersPaidCents = (int) $clearedTxns->sum('amount');

        $pendingTxns = $transactions->filter(fn ($t) => $t->status instanceof \CorvMC\Finance\States\TransactionState\Pending);
        $ordersPendingCents = (int) $pendingTxns->sum('amount');
        $ordersPendingCount = $pendingTxns->count();

        $monthlyOrders = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->whereNotState('status', Cancelled::class)
            ->with('lineItems')
            ->get();
        // Discounts are negative LineItem amounts, abs() to get the value
        $creditsAppliedCents = (int) abs($monthlyOrders->flatMap->lineItems->filter->isDiscount()->sum('amount'));

        // Stripe payments (for fee calculation)
        $stripePaymentsCents = $byCurrency->get('stripe')['total'] ?? 0;
        $stripePaymentsCount = $byCurrency->get('stripe')['count'] ?? 0;
        $orderFeesCents = ($stripePaymentsCount * 30) + (int) round($stripePaymentsCents * 0.029);

        // Cash collected (no fees)
        $cashCollectedCents = $byCurrency->get('cash')['total'] ?? 0;

        // Combined totals
        $totalGrossRevenueCents = $mrrTotalCents + $ordersPaidCents;
        $totalFeesCents = $subscriptionFeesCents + $orderFeesCents;
        $totalNetRevenueCents = $totalGrossRevenueCents - $totalFeesCents;

        return [
            // Membership stats
            'sustaining_members' => $stats->sustaining_members,
            'subscription_net_change' => $stats->subscription_net_change_last_month,
            'active_subscriptions' => $subscriptionCount,
            'new_members_this_month' => $stats->new_members_this_month,
            'average_contribution' => $stats->average_mrr->formatTo('en_US'),
            'median_contribution' => $stats->median_contribution->formatTo('en_US'),

            // Revenue breakdown
            'subscriptions_total' => Money::ofMinor($mrrTotalCents, 'USD')->formatTo('en_US'),
            'charges_collected' => Money::ofMinor($ordersPaidCents, 'USD')->formatTo('en_US'),
            'charges_pending' => Money::ofMinor($ordersPendingCents, 'USD')->formatTo('en_US'),
            'charges_pending_count' => $ordersPendingCount,
            'credits_applied' => Money::ofMinor($creditsAppliedCents, 'USD')->formatTo('en_US'),
            'cash_collected' => Money::ofMinor($cashCollectedCents, 'USD')->formatTo('en_US'),

            // Totals
            'total_gross' => Money::ofMinor($totalGrossRevenueCents, 'USD')->formatTo('en_US'),
            'total_fees' => Money::ofMinor($totalFeesCents, 'USD')->formatTo('en_US'),
            'total_net' => Money::ofMinor($totalNetRevenueCents, 'USD')->formatTo('en_US'),

            // For detailed breakdown
            'by_payment_method' => $byCurrency->values()->toArray(),
        ];
    }

    /**
     * Get recent activities for the activity feed.
     */
    public function getRecentActivities(): \Illuminate\Support\Collection
    {
        // Filter out activities with subject types that no longer exist
        return Activity::with(['causer', 'subject'])
            ->whereNotNull('subject_type')
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (Activity $activity) {
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
