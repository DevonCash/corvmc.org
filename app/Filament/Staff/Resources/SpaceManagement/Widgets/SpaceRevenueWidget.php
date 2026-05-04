<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Widgets;

use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class SpaceRevenueWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $monthRevenue = $this->settledRevenueForPeriod(now()->startOfMonth(), now()->endOfMonth());
        $weekRevenue = $this->settledRevenueForPeriod(now()->startOfWeek(), now()->endOfWeek());
        $pending = $this->pendingRevenueForPeriod(now()->startOfMonth(), now()->endOfMonth());

        $parts = ['$' . number_format($weekRevenue / 100, 2) . ' this week'];
        if ($pending > 0) {
            $parts[] = '$' . number_format($pending / 100, 2) . ' pending';
        }

        return [
            Stat::make('Revenue', new HtmlString('$' . number_format($monthRevenue / 100, 2) . ' <span class="text-sm font-normal text-gray-500 dark:text-gray-400">this month</span>'))
                ->description(implode(' • ', $parts))
                ->color('primary')
                ->icon('tabler-cash'),
        ];
    }

    /**
     * Sum total_amount of settled Orders with rehearsal_time line items in the given period.
     */
    private function settledRevenueForPeriod($start, $end): int
    {
        return Order::query()
            ->whereIn('status', [OrderState\Completed::$name, OrderState\Comped::$name])
            ->whereBetween('settled_at', [$start, $end])
            ->whereHas('lineItems', fn ($q) => $q->where('product_type', 'rehearsal_time'))
            ->sum('total_amount');
    }

    /**
     * Sum total_amount of pending Orders with rehearsal_time line items in the given period.
     */
    private function pendingRevenueForPeriod($start, $end): int
    {
        return Order::query()
            ->where('status', OrderState\Pending::$name)
            ->whereBetween('created_at', [$start, $end])
            ->whereHas('lineItems', fn ($q) => $q->where('product_type', 'rehearsal_time'))
            ->sum('total_amount');
    }
}
