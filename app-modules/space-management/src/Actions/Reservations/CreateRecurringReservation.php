<?php

namespace CorvMC\SpaceManagement\Actions\Reservations;

use App\Models\User;
use Carbon\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateRecurringReservation
{
    use AsAction;

    /**
     * Create recurring reservations for sustaining members.
     *
     * Creates multiple reservation instances based on a recurrence pattern.
     * Provides credit estimate for informational purposes - user can pay for any shortfall.
     * Skips slots with conflicts but continues with remaining slots.
     *
     * @param  User  $user  User creating the reservations (must be sustaining member)
     * @param  Carbon  $startTime  Initial start time
     * @param  Carbon  $endTime  Initial end time
     * @param  array  $recurrencePattern  Pattern config with 'weeks' and 'interval' keys
     * @return array Array with 'reservations' and 'credit_estimate' keys
     *
     * @throws \InvalidArgumentException If user is not a sustaining member
     */
    public function handle(User $user, Carbon $startTime, Carbon $endTime, array $recurrencePattern): array
    {
        if (! $user->isSustainingMember()) {
            throw new \InvalidArgumentException('Only sustaining members can create recurring reservations.');
        }

        // Estimate credit availability across renewal cycles (informational only)
        $creditEstimate = EstimateRecurringCreditCost::run($user, $startTime, $endTime, $recurrencePattern);

        $reservations = [];
        $weeks = $recurrencePattern['weeks'] ?? 4; // Default to 4 weeks
        $interval = $recurrencePattern['interval'] ?? 1; // Every N weeks

        for ($i = 0; $i < $weeks; $i++) {
            $weekOffset = $i * $interval;
            $recurringStart = $startTime->copy()->addWeeks($weekOffset);
            $recurringEnd = $endTime->copy()->addWeeks($weekOffset);

            try {
                $reservation = CreateReservation::run($user, $recurringStart, $recurringEnd, [
                    'is_recurring' => true,
                    'recurrence_pattern' => $recurrencePattern,
                    'status' => 'pending', // Recurring reservations need confirmation
                ]);

                $reservations[] = $reservation;
            } catch (\InvalidArgumentException $e) {
                // Skip this slot if there's a conflict, but continue with others
                continue;
            }
        }

        return [
            'reservations' => $reservations,
            'credit_estimate' => $creditEstimate,
        ];
    }
}
