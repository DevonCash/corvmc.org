<?php

namespace App\Actions\Reservations;

use CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours;
use App\Enums\CreditType;
use App\Models\CreditTransaction;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class EstimateRecurringCreditCost
{
    use AsAction;

    /**
     * Estimate if user will have enough credits for recurring reservations.
     *
     * This looks ahead across credit renewal cycles to determine if the user
     * will have sufficient credits for all recurring instances.
     *
     * @return array{
     *     sufficient: bool,
     *     total_blocks_needed: int,
     *     current_balance: int,
     *     estimated_allocations: array,
     *     shortfall: int
     * }
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        $weeks = $recurrencePattern['weeks'] ?? 4;
        $interval = $recurrencePattern['interval'] ?? 1;

        // Calculate blocks needed per instance
        $hours = $startTime->diffInMinutes($endTime) / 60;
        $blocksPerInstance = Reservation::hoursToBlocks($hours);
        $totalBlocksNeeded = $blocksPerInstance * $weeks;

        // Get current credit balance
        $currentBalance = $user->getCreditBalance(CreditType::FreeHours);

        // Get monthly allocation amount
        $monthlyFreeHours = GetUserMonthlyFreeHours::run($user);
        $monthlyBlockAllocation = Reservation::hoursToBlocks($monthlyFreeHours);

        // Find last allocation date to predict next allocations
        $lastAllocation = CreditTransaction::where('user_id', $user->id)
            ->where('credit_type', CreditType::FreeHours->value)
            ->where('source', 'monthly_reset')
            ->latest('created_at')
            ->first();

        // Simulate credit availability across the recurring period
        $simulatedBalance = $currentBalance;
        $estimatedAllocations = [];
        $lastAllocationDate = $lastAllocation ? $lastAllocation->created_at : now();

        // Calculate when reservations will be confirmed (3 days before each)
        $confirmationDates = [];
        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            // Credits are deducted 3 days before reservation (confirmation deadline)
            $confirmationDate = $recurringStart->copy()->subDays(3);
            $confirmationDates[] = $confirmationDate;
        }

        // Sort confirmation dates
        sort($confirmationDates);

        // Simulate credit allocations and deductions
        $nextAllocationDate = $lastAllocationDate->copy()->addMonthNoOverflow();
        foreach ($confirmationDates as $confirmationDate) {
            // Check if we'll get a credit allocation before this confirmation
            while ($nextAllocationDate->lte($confirmationDate)) {
                $simulatedBalance += $monthlyBlockAllocation;
                $estimatedAllocations[] = [
                    'date' => $nextAllocationDate->toDateString(),
                    'amount' => $monthlyBlockAllocation,
                ];
                $nextAllocationDate = $nextAllocationDate->copy()->addMonthNoOverflow();
            }

            // Deduct blocks for this confirmation
            $simulatedBalance -= $blocksPerInstance;
        }

        $sufficient = $simulatedBalance >= 0;
        $shortfall = max(0, -$simulatedBalance);

        return [
            'sufficient' => $sufficient,
            'total_blocks_needed' => $totalBlocksNeeded,
            'blocks_per_instance' => $blocksPerInstance,
            'current_balance' => $currentBalance,
            'monthly_allocation' => $monthlyBlockAllocation,
            'estimated_allocations' => $estimatedAllocations,
            'final_balance' => $simulatedBalance,
            'shortfall' => $shortfall,
        ];
    }
}
