<?php

namespace CorvMC\Finance\Console\Commands;

use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\TicketOrder;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTicketOrdersToOrders extends Command
{
    protected $signature = 'finance:backfill-ticket-orders {--dry-run : Show what would be created without writing}';

    protected $description = 'Backfill existing TicketOrders into the new Order/LineItem/Transaction system';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no data will be written.');
        }

        $ticketOrders = TicketOrder::with(['event', 'user'])->get();
        $this->info("Found {$ticketOrders->count()} ticket orders to backfill.");

        $stats = ['created' => 0, 'skipped' => 0, 'completed' => 0, 'refunded' => 0, 'cancelled' => 0, 'pending' => 0];

        foreach ($ticketOrders as $ticketOrder) {
            // Skip if an Order already exists
            $existingOrder = Order::whereHas('lineItems', function ($q) use ($ticketOrder) {
                $q->where('product_type', 'event_ticket')
                    ->where('product_id', $ticketOrder->id);
            })->first();

            if ($existingOrder) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->line("  Would create Order for TicketOrder #{$ticketOrder->id} ({$ticketOrder->status->value})");
                $stats['created']++;
                continue;
            }

            try {
                DB::transaction(function () use ($ticketOrder, &$stats) {
                    $this->backfillTicketOrder($ticketOrder, $stats);
                });
                $stats['created']++;
            } catch (\Exception $e) {
                $this->error("  Failed TicketOrder #{$ticketOrder->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Backfill complete:');
        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [ucfirst($k), $v])->values()->all()
        );

        return self::SUCCESS;
    }

    private function backfillTicketOrder(TicketOrder $ticketOrder, array &$stats): void
    {
        $subtotalCents = $ticketOrder->subtotal->getMinorAmount()->toInt();
        $discountCents = $ticketOrder->discount?->getMinorAmount()?->toInt() ?? 0;
        $feesCents = $ticketOrder->fees?->getMinorAmount()?->toInt() ?? 0;
        $totalCents = $ticketOrder->total->getMinorAmount()->toInt();

        $orderStatus = match ($ticketOrder->status) {
            TicketOrderStatus::Completed => Completed::class,
            TicketOrderStatus::Refunded => Refunded::class,
            TicketOrderStatus::Cancelled => Cancelled::class,
            TicketOrderStatus::Pending => Pending::class,
        };

        $order = Order::create([
            'user_id' => $ticketOrder->user_id,
            'status' => $orderStatus,
            'total_amount' => $totalCents,
            'settled_at' => $ticketOrder->completed_at,
            'notes' => 'backfilled from ticket order',
            'created_at' => $ticketOrder->created_at,
            'updated_at' => $ticketOrder->updated_at,
        ]);

        // Base ticket LineItem
        $eventTitle = $ticketOrder->event?->title ?? 'Event';
        $unitPriceCents = $ticketOrder->unit_price->getMinorAmount()->toInt();

        LineItem::create([
            'order_id' => $order->id,
            'product_type' => 'event_ticket',
            'product_id' => $ticketOrder->id,
            'description' => "{$ticketOrder->quantity} ticket(s) for {$eventTitle}",
            'unit' => 'ticket',
            'unit_price' => $unitPriceCents,
            'quantity' => $ticketOrder->quantity,
            'amount' => $subtotalCents,
            'created_at' => $ticketOrder->created_at,
        ]);

        // Discount LineItem
        if ($discountCents > 0) {
            LineItem::create([
                'order_id' => $order->id,
                'product_type' => 'free_hours_discount',
                'product_id' => null,
                'description' => 'Sustaining member discount',
                'unit' => 'discount',
                'unit_price' => -$discountCents,
                'quantity' => 1,
                'amount' => -$discountCents,
                'created_at' => $ticketOrder->created_at,
            ]);
        }

        // Fee LineItem
        if ($feesCents > 0) {
            LineItem::create([
                'order_id' => $order->id,
                'product_type' => 'processing_fee',
                'product_id' => null,
                'description' => 'Processing fee',
                'unit' => 'fee',
                'unit_price' => $feesCents,
                'quantity' => 1,
                'amount' => $feesCents,
                'created_at' => $ticketOrder->created_at,
            ]);
        }

        // Payment Transaction
        if (in_array($ticketOrder->status, [TicketOrderStatus::Completed, TicketOrderStatus::Pending])) {
            $currency = match ($ticketOrder->payment_method) {
                'stripe' => 'stripe',
                'cash', 'manual', 'door_sale' => 'cash',
                default => 'stripe',
            };

            Transaction::create([
                'order_id' => $order->id,
                'user_id' => $ticketOrder->user_id,
                'currency' => $currency,
                'amount' => $totalCents,
                'type' => 'payment',
                'status' => $ticketOrder->status === TicketOrderStatus::Completed ? Cleared::class : TransactionPending::class,
                'cleared_at' => $ticketOrder->completed_at,
                'metadata' => null,
                'created_at' => $ticketOrder->created_at,
                'updated_at' => $ticketOrder->updated_at,
            ]);
        }

        $statusKey = $ticketOrder->status->value;
        $stats[$statusKey]++;

        $this->line("  Created Order #{$order->id} for TicketOrder #{$ticketOrder->id} ({$statusKey})");
    }
}
