<?php

namespace App\Finance\Products;

use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Products\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Finance Product wrapping TicketOrder.
 *
 * Pricing comes from the TicketOrder's unit_price (Brick\Money).
 * One billable unit = one ticket. No wallet discounts currently.
 */
class TicketProduct extends Product
{
    public static string $type = 'event_ticket';

    public static ?string $model = TicketOrder::class;

    public static function getBillableUnits(?Model $model = null): float
    {
        if (! $model) {
            return 0;
        }

        return (float) $model->quantity;
    }

    public static function getPricePerUnit(?Model $model = null): int
    {
        if (! $model) {
            return 0;
        }

        return $model->unit_price?->getMinorAmount()?->toInt() ?? 0;
    }

    public static function getDescription(?Model $model = null): string
    {
        if (! $model) {
            return 'Event Ticket';
        }

        $eventTitle = $model->event?->title ?? 'Event';
        $date = $model->event?->start_datetime?->format('M j, Y') ?? 'TBD';

        return "{$model->quantity} ticket(s) for {$eventTitle} on {$date}";
    }

    public static function getEligibleWallets(?Model $model = null): array
    {
        return [];
    }

    public static function getUnit(): string
    {
        return 'ticket';
    }
}
