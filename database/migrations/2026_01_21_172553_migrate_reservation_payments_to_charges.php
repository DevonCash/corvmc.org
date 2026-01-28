<?php

use App\Models\User;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates existing payment data from reservations to the new charges table.
     */
    public function up(): void
    {
        // Get valid user IDs to avoid foreign key violations
        $validUserIds = User::pluck('id')->toArray();

        // Get all rehearsal reservations that don't have a charge yet
        $reservations = RehearsalReservation::withoutGlobalScopes()
            ->whereNotIn('id', Charge::where('chargeable_type', RehearsalReservation::class)->pluck('chargeable_id'))
            ->get();

        $migrated = 0;
        $skipped = 0;

        foreach ($reservations as $reservation) {
            // Determine user_id
            $userId = $reservation->user_id ?? $reservation->reservable_id;

            // Skip if user doesn't exist (orphaned reservation)
            if (! in_array($userId, $validUserIds)) {
                $skipped++;
                Log::warning("Skipping charge creation for orphaned reservation #{$reservation->id} - user {$userId} not found");
                continue;
            }

            // Skip if no cost data
            if (! $reservation->cost) {
                $skipped++;
                continue;
            }

            // Map old payment_status to ChargeStatus
            $status = $this->mapPaymentStatus($reservation->payment_status?->value);

            // Calculate credits applied from free_hours_used
            $creditsApplied = null;
            if ($reservation->free_hours_used > 0) {
                $minutesPerBlock = 30;
                $blocks = (int) ceil(($reservation->free_hours_used * 60) / $minutesPerBlock);
                $creditsApplied = ['free_hours' => $blocks];
            }

            // Calculate gross amount (cost + value of free hours used)
            $hourlyRateCents = 1500; // $15/hour
            $freeHoursValue = (int) ($reservation->free_hours_used * $hourlyRateCents);
            $netAmount = $reservation->cost->getMinorAmount()->toInt();
            $grossAmount = $netAmount + $freeHoursValue;

            // Create charge record
            Charge::create([
                'user_id' => $userId,
                'chargeable_type' => RehearsalReservation::class,
                'chargeable_id' => $reservation->id,
                'amount' => $grossAmount,
                'credits_applied' => $creditsApplied,
                'net_amount' => $netAmount,
                'status' => $status,
                'payment_method' => $reservation->payment_method,
                'paid_at' => $reservation->paid_at,
                'stripe_session_id' => $reservation->stripe_payment_intent_id ?? null,
                'notes' => $reservation->payment_notes,
                'created_at' => $reservation->created_at,
                'updated_at' => $reservation->updated_at,
            ]);

            $migrated++;
        }

        Log::info("Migration complete: {$migrated} charges created, {$skipped} skipped");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete all charges for rehearsal reservations
        Charge::where('chargeable_type', RehearsalReservation::class)->delete();
    }

    /**
     * Map old PaymentStatus values to ChargeStatus.
     */
    protected function mapPaymentStatus(?string $paymentStatus): ChargeStatus
    {
        return match ($paymentStatus) {
            'paid' => ChargeStatus::Paid,
            'comped' => ChargeStatus::Comped,
            'refunded' => ChargeStatus::Refunded,
            'n/a' => ChargeStatus::Paid, // N/A means no payment needed (free)
            default => ChargeStatus::Pending, // unpaid or null
        };
    }
};
