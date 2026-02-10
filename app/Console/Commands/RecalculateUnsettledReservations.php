<?php

namespace App\Console\Commands;

use Brick\Money\Money;
use CorvMC\Finance\Actions\Pricing\CalculatePriceForUser;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateUnsettledReservations extends Command
{
    protected $signature = 'reservations:recalculate-unsettled
        {--dry-run : Show what would change without making updates}
        {--all : Include all reservations, not just unsettled ones}
        {--recalculate-credits : Recalculate credit eligibility (default: preserve existing credits)}';

    protected $description = 'Recalculate charges for unsettled rehearsal reservations';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $includeAll = $this->option('all');
        $recalculateCredits = $this->option('recalculate-credits');

        $this->info('Recalculating rehearsal reservation charges...');
        $this->line('==============================================');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        if ($recalculateCredits) {
            $this->warn('RECALCULATE CREDITS MODE - Credit eligibility will be recalculated');
        } else {
            $this->info('Existing credits will be preserved');
        }

        $query = RehearsalReservation::query()
            ->with(['charge', 'reservable']);

        if (! $includeAll) {
            // Include reservations without charges OR with pending charges
            $query->where(function ($q) {
                $q->whereDoesntHave('charge')
                    ->orWhereHas('charge', function ($chargeQuery) {
                        $chargeQuery->where('status', ChargeStatus::Pending);
                    });
            });
        }

        $reservations = $query->get();

        if ($reservations->isEmpty()) {
            $this->info('No reservations found to process.');

            return 0;
        }

        $this->info("Found {$reservations->count()} reservation(s) to process.");
        $this->newLine();

        $results = [
            'processed' => 0,
            'created' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($reservations as $reservation) {
            $result = $this->processReservation($reservation, $isDryRun, $recalculateCredits);
            $results['processed']++;

            if ($result['error']) {
                $results['errors']++;
                $this->error("  Error: {$result['error']}");
            } elseif ($result['created'] ?? false) {
                $results['created']++;
                $results['details'][] = $result;
            } elseif ($result['changed']) {
                $results['changed']++;
                $results['details'][] = $result;
            } else {
                $results['unchanged']++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Processed: {$results['processed']}");
        $this->line("  Created: {$results['created']}");
        $this->line("  Changed: {$results['changed']}");
        $this->line("  Unchanged: {$results['unchanged']}");
        $this->line("  Errors: {$results['errors']}");

        if ($isDryRun && $results['changed'] > 0) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return $results['errors'] > 0 ? 1 : 0;
    }

    /**
     * @return array{changed: bool, created?: bool, error: string|null, old_amount?: int, new_amount?: int, old_net?: int, new_net?: int}
     */
    protected function processReservation(RehearsalReservation $reservation, bool $isDryRun, bool $recalculateCredits): array
    {
        try {
            $user = $reservation->getBillableUser();
        } catch (\Throwable $e) {
            $reserverName = $reservation->reservable?->name ?? 'Unknown';
            $date = $reservation->reserved_at?->format('M j, Y g:ia') ?? 'Unknown date';
            $this->warn("- {$reserverName} ({$date}): Skipped - {$e->getMessage()}");

            return ['changed' => false, 'error' => $e->getMessage()];
        }

        try {
            // Get fresh pricing (this calculates based on current hours and rate)
            $pricing = CalculatePriceForUser::run($reservation, $user);
        } catch (\Throwable $e) {
            return ['changed' => false, 'error' => $e->getMessage()];
        }

        /** @var Charge|null $charge */
        $charge = $reservation->charge;

        // If no charge exists, create one
        if (! $charge) {
            return $this->createChargeForReservation($reservation, $user, $pricing, $isDryRun);
        }

        // Convert Money objects to cents for comparison
        $oldAmount = $charge->amount->getMinorAmount()->toInt();
        $oldNetAmount = $charge->net_amount->getMinorAmount()->toInt();
        $oldCredits = $charge->credits_applied ?? [];

        // Determine new values based on whether we're recalculating credits
        if ($recalculateCredits) {
            // Full recalculation - use new pricing as-is
            $newAmount = $pricing->amount;
            $newCredits = $pricing->credits_applied;
            $newNetAmount = $pricing->net_amount;
        } else {
            // Preserve existing credits - only recalculate amount based on hours
            $newAmount = $pricing->amount;
            $newCredits = $oldCredits;

            // Calculate credit value from preserved credits
            $creditValue = 0;
            foreach ($newCredits as $creditTypeKey => $blocks) {
                $creditValuePerBlock = config("finance.credits.value.{$creditTypeKey}", 0);
                $creditValue += $blocks * $creditValuePerBlock;
            }

            // Net amount is new amount minus credit value (but not less than 0)
            $newNetAmount = max(0, $newAmount - $creditValue);
        }

        $hasChanges = $newAmount !== $oldAmount
            || $newNetAmount !== $oldNetAmount
            || $newCredits !== $oldCredits;

        $reserverName = $reservation->getResponsibleUser()?->name ?? 'Unknown';
        $date = $reservation->reserved_at->format('M j, Y g:ia');

        if (! $hasChanges) {
            $this->line("- {$reserverName} ({$date}): No changes needed");

            return ['changed' => false, 'error' => null];
        }

        $this->line("- {$reserverName} ({$date}):");
        $this->line("    Amount: \${$this->formatCents($oldAmount)} -> \${$this->formatCents($newAmount)}");
        $this->line("    Net:    \${$this->formatCents($oldNetAmount)} -> \${$this->formatCents($newNetAmount)}");

        if ($oldCredits !== $newCredits) {
            $oldCreditsStr = empty($oldCredits) ? 'none' : json_encode($oldCredits);
            $newCreditsStr = empty($newCredits) ? 'none' : json_encode($newCredits);
            $this->line("    Credits: {$oldCreditsStr} -> {$newCreditsStr}");
        }

        if ($isDryRun) {
            return [
                'changed' => true,
                'error' => null,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'old_net' => $oldNetAmount,
                'new_net' => $newNetAmount,
            ];
        }

        DB::transaction(function () use ($reservation, $charge, $user, $oldCredits, $newAmount, $newCredits, $newNetAmount, $recalculateCredits) {
            // Only adjust credits if we're recalculating them
            if ($recalculateCredits) {
                $this->adjustCredits($user, $charge, $oldCredits, $newCredits);
            }

            $charge->update([
                'amount' => Money::ofMinor($newAmount, 'USD'),
                'credits_applied' => $newCredits ?: null,
                'net_amount' => Money::ofMinor($newNetAmount, 'USD'),
            ]);

            if ($newNetAmount === 0 && $charge->status->isPending()) {
                $charge->markAsCoveredByCredits();
            } elseif ($newNetAmount > 0 && $charge->status->isCoveredByCredits()) {
                $charge->update([
                    'status' => ChargeStatus::Pending,
                    'payment_method' => null,
                    'paid_at' => null,
                ]);
            }

            if ($reservation->isFillable('free_hours_used')) {
                $freeHoursBlocks = $newCredits['free_hours'] ?? 0;
                $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
                $reservation->updateQuietly([
                    'free_hours_used' => ($freeHoursBlocks * $minutesPerBlock) / 60,
                ]);
            }
        });

        $this->info('    Updated successfully');

        return [
            'changed' => true,
            'error' => null,
            'old_amount' => $oldAmount,
            'new_amount' => $newAmount,
            'old_net' => $oldNetAmount,
            'new_net' => $newNetAmount,
        ];
    }

    /**
     * Create a new charge for a reservation that doesn't have one.
     *
     * @return array{changed: bool, created: bool, error: string|null, new_amount?: int, new_net?: int}
     */
    protected function createChargeForReservation(
        RehearsalReservation $reservation,
        \App\Models\User $user,
        \CorvMC\Finance\Data\PriceCalculationData $pricing,
        bool $isDryRun
    ): array {
        $reserverName = $reservation->getResponsibleUser()?->name ?? 'Unknown';
        $date = $reservation->reserved_at->format('M j, Y g:ia');

        $this->line("- {$reserverName} ({$date}):");
        $this->warn("    MISSING CHARGE - Creating new charge");
        $this->line("    Amount: \${$this->formatCents($pricing->amount)}");
        $this->line("    Net:    \${$this->formatCents($pricing->net_amount)}");

        if (! empty($pricing->credits_applied)) {
            $this->line("    Credits: " . json_encode($pricing->credits_applied));
        }

        if ($isDryRun) {
            return [
                'changed' => false,
                'created' => true,
                'error' => null,
                'new_amount' => $pricing->amount,
                'new_net' => $pricing->net_amount,
            ];
        }

        DB::transaction(function () use ($reservation, $user, $pricing) {
            // Create the charge
            $charge = Charge::createForChargeable(
                $reservation,
                $pricing->amount,
                $pricing->net_amount,
                $pricing->credits_applied ?: null
            );

            // Mark as covered by credits if fully covered
            if ($pricing->net_amount === 0) {
                $charge->markAsCoveredByCredits();
            }

            // Update derived fields on reservation
            if ($reservation->isFillable('free_hours_used')) {
                $freeHoursBlocks = $pricing->credits_applied['free_hours'] ?? 0;
                $minutesPerBlock = config('finance.credits.minutes_per_block', 30);
                $reservation->updateQuietly([
                    'free_hours_used' => ($freeHoursBlocks * $minutesPerBlock) / 60,
                ]);
            }

            // Deduct credits from user
            if (! empty($pricing->credits_applied)) {
                foreach ($pricing->credits_applied as $creditTypeKey => $blocks) {
                    if ($blocks > 0) {
                        $creditType = CreditType::from($creditTypeKey);
                        $user->deductCredit(
                            $blocks,
                            $creditType,
                            'charge_created',
                            $charge->id
                        );
                    }
                }
            }
        });

        $this->info('    Created successfully');

        return [
            'changed' => false,
            'created' => true,
            'error' => null,
            'new_amount' => $pricing->amount,
            'new_net' => $pricing->net_amount,
        ];
    }

    /**
     * @param  array<string, int>  $oldCredits
     * @param  array<string, int>  $newCredits
     */
    protected function adjustCredits($user, Charge $charge, array $oldCredits, array $newCredits): void
    {
        $allTypes = array_unique(array_merge(array_keys($oldCredits), array_keys($newCredits)));

        foreach ($allTypes as $creditTypeKey) {
            $oldBlocks = $oldCredits[$creditTypeKey] ?? 0;
            $newBlocks = $newCredits[$creditTypeKey] ?? 0;
            $difference = $newBlocks - $oldBlocks;

            if ($difference === 0) {
                continue;
            }

            $creditType = CreditType::from($creditTypeKey);

            if ($difference > 0) {
                $user->deductCredit(
                    $difference,
                    $creditType,
                    'charge_recalculation',
                    $charge->id
                );
            } else {
                $user->addCredit(
                    abs($difference),
                    $creditType,
                    'charge_recalculation',
                    $charge->id,
                    'Refund from charge recalculation'
                );
            }
        }
    }

    protected function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
