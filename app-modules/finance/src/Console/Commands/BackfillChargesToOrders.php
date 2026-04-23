<?php

namespace CorvMC\Finance\Console\Commands;

use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\Finance\Models\LineItem;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillChargesToOrders extends Command
{
    protected $signature = 'finance:backfill-charges {--dry-run : Show what would be created without writing}';

    protected $description = 'Backfill existing Charges into the new Order/LineItem/Transaction system';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN — no data will be written.');
        }

        $charges = Charge::with(['user', 'chargeable'])->get();
        $this->info("Found {$charges->count()} charges to backfill.");

        $stats = [
            'created' => 0,
            'skipped' => 0,
            'completed' => 0,
            'comped' => 0,
            'cancelled' => 0,
            'refunded' => 0,
            'pending' => 0,
            'covered_by_credits' => 0,
        ];

        foreach ($charges as $charge) {
            // Skip if an Order already exists for this chargeable
            $existingOrder = Order::whereHas('lineItems', function ($q) use ($charge) {
                $q->where('product_id', $charge->chargeable_id)
                    ->where('product_type', $this->resolveProductType($charge->chargeable_type));
            })->first();

            if ($existingOrder) {
                $stats['skipped']++;
                $this->line("  Skipped Charge #{$charge->id} — Order #{$existingOrder->id} already exists");

                continue;
            }

            if ($dryRun) {
                $this->line("  Would create Order for Charge #{$charge->id} ({$charge->status->value})");
                $stats['created']++;

                continue;
            }

            try {
                DB::transaction(function () use ($charge, &$stats) {
                    $this->backfillCharge($charge, $stats);
                });
                $stats['created']++;
            } catch (\Exception $e) {
                $this->error("  Failed Charge #{$charge->id}: {$e->getMessage()}");
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

    private function backfillCharge(Charge $charge, array &$stats): void
    {
        $grossCents = $charge->amount->getMinorAmount()->toInt();
        $netCents = $charge->net_amount->getMinorAmount()->toInt();
        $productType = $this->resolveProductType($charge->chargeable_type);

        // Determine Order status
        $orderStatus = match ($charge->status) {
            ChargeStatus::Paid => Completed::class,
            ChargeStatus::CoveredByCredits => Completed::class,
            ChargeStatus::Comped => Comped::class,
            ChargeStatus::Refunded => Refunded::class,
            ChargeStatus::Cancelled => Cancelled::class,
            ChargeStatus::Pending => Pending::class,
        };

        // Create Order
        $order = Order::create([
            'user_id' => $charge->user_id,
            'status' => $orderStatus,
            'total_amount' => $netCents,
            'settled_at' => $charge->paid_at,
            'notes' => $charge->notes ? $charge->notes . ' [backfilled]' : 'backfilled',
            'created_at' => $charge->created_at,
            'updated_at' => $charge->updated_at,
        ]);

        // Base LineItem
        $chargeable = $charge->chargeable;
        $duration = 0;

        if ($chargeable && method_exists($chargeable, 'getDurationAttribute')) {
            $duration = $chargeable->duration;
        }

        LineItem::create([
            'order_id' => $order->id,
            'product_type' => $productType,
            'product_id' => $charge->chargeable_id,
            'description' => $this->buildDescription($charge, $chargeable),
            'unit' => 'hour',
            'unit_price' => $duration > 0 ? (int) round($grossCents / $duration) : $grossCents,
            'quantity' => $duration > 0 ? $duration : 1,
            'amount' => $grossCents,
            'created_at' => $charge->created_at,
        ]);

        // Discount LineItems from credits_applied
        if ($charge->credits_applied && $grossCents !== $netCents) {
            $discountCents = $grossCents - $netCents;

            foreach ($charge->credits_applied as $creditType => $blocks) {
                $walletKey = strtolower(str_replace(' ', '_', $creditType));
                $centsPerBlock = config("finance.wallets.{$walletKey}.cents_per_unit", 750);

                LineItem::create([
                    'order_id' => $order->id,
                    'product_type' => $walletKey . '_discount',
                    'product_id' => null,
                    'description' => ucfirst(str_replace('_', ' ', $walletKey)) . " discount ({$blocks} blocks)",
                    'unit' => 'discount',
                    'unit_price' => -$centsPerBlock,
                    'quantity' => $blocks,
                    'amount' => -min($blocks * $centsPerBlock, $discountCents),
                    'created_at' => $charge->created_at,
                ]);
            }
        }

        // Payment Transaction (if applicable)
        if (in_array($charge->status, [ChargeStatus::Paid, ChargeStatus::Pending])) {
            $currency = match ($charge->payment_method) {
                'stripe' => 'stripe',
                'cash', 'manual' => 'cash',
                default => 'cash',
            };

            $txnStatus = $charge->status === ChargeStatus::Paid
                ? Cleared::class
                : TransactionPending::class;

            $metadata = [];
            if ($charge->stripe_session_id) {
                $metadata['session_id'] = $charge->stripe_session_id;
            }
            if ($charge->stripe_payment_intent_id) {
                $metadata['payment_intent_id'] = $charge->stripe_payment_intent_id;
            }

            Transaction::create([
                'order_id' => $order->id,
                'user_id' => $charge->user_id,
                'currency' => $currency,
                'amount' => $netCents,
                'type' => 'payment',
                'status' => $txnStatus,
                'cleared_at' => $charge->status === ChargeStatus::Paid ? $charge->paid_at : null,
                'metadata' => $metadata ?: null,
                'created_at' => $charge->created_at,
                'updated_at' => $charge->updated_at,
            ]);
        }

        // Track status counts
        $statusKey = match ($charge->status) {
            ChargeStatus::Paid => 'completed',
            ChargeStatus::CoveredByCredits => 'covered_by_credits',
            ChargeStatus::Comped => 'comped',
            ChargeStatus::Refunded => 'refunded',
            ChargeStatus::Cancelled => 'cancelled',
            ChargeStatus::Pending => 'pending',
        };
        $stats[$statusKey]++;

        $this->line("  Created Order #{$order->id} for Charge #{$charge->id} ({$charge->status->value})");
    }

    private function resolveProductType(string $chargeableType): string
    {
        return match ($chargeableType) {
            'rehearsal_reservation', 'reservation' => 'rehearsal_time',
            'equipment_loan' => 'equipment_loan',
            'ticket_order' => 'event_ticket',
            default => $chargeableType,
        };
    }

    private function buildDescription(Charge $charge, $chargeable): string
    {
        if ($chargeable && $chargeable->reserved_at) {
            $date = $chargeable->reserved_at->format('M j, Y');
            $hours = $chargeable->duration ?? '?';

            return "Practice Space - {$hours} hour(s) on {$date}";
        }

        return "Charge #{$charge->id} [backfilled]";
    }
}
