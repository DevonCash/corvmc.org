<?php

use App\Models\User;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * First migrates any remaining payment data to charges table,
     * then removes legacy payment fields from reservations table.
     */
    public function up(): void
    {
        // Ensure all existing payment data is migrated to charges
        $this->migratePaymentDataToCharges();

        // Drop the legacy columns
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'cost',
                'payment_status',
                'payment_method',
                'paid_at',
                'payment_notes',
            ]);
        });
    }

    /**
     * Migrate any reservations without charges to the charges table.
     */
    protected function migratePaymentDataToCharges(): void
    {
        // Get valid user IDs to avoid foreign key violations
        $validUserIds = User::pluck('id')->toArray();

        // Get all rehearsal reservations that don't have a charge yet
        // Use raw query to avoid model issues with columns being removed
        $reservations = DB::table('reservations')
            ->where('type', RehearsalReservation::class)
            ->whereNotIn('id', DB::table('charges')->where('chargeable_type', RehearsalReservation::class)->pluck('chargeable_id'))
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

            // Skip if no cost data (cost stored as decimal in cents, but check for 0)
            if (! $reservation->cost || $reservation->cost == 0) {
                // Still create a charge for $0 reservations so they have payment tracking
                $netAmount = 0;
            } else {
                // Cost is stored as decimal dollars, convert to cents
                $netAmount = (int) ($reservation->cost * 100);
            }

            // Map old payment_status to ChargeStatus
            $status = $this->mapPaymentStatus($reservation->payment_status);

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
            $grossAmount = $netAmount + $freeHoursValue;

            // Insert directly via DB to bypass deleted Charge model and MoneyCast
            DB::table('charges')->insert([
                'user_id' => $userId,
                'chargeable_type' => RehearsalReservation::class,
                'chargeable_id' => $reservation->id,
                'amount' => $grossAmount,
                'credits_applied' => $creditsApplied ? json_encode($creditsApplied) : null,
                'net_amount' => $netAmount,
                'status' => $status,
                'payment_method' => $reservation->payment_method,
                'paid_at' => $reservation->paid_at,
                'notes' => $reservation->payment_notes,
                'created_at' => $reservation->created_at,
                'updated_at' => $reservation->updated_at,
            ]);

            $migrated++;
        }

        Log::info("Payment data migration complete: {$migrated} charges created, {$skipped} skipped");
    }

    /**
     * Map old PaymentStatus values to charge status strings.
     */
    protected function mapPaymentStatus(?string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'paid',
            'comped' => 'comped',
            'refunded' => 'refunded',
            'n/a' => 'paid', // N/A means no payment needed (free)
            default => 'pending', // unpaid or null
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('cost', 8, 2)->default(0)->after('status');
            $table->string('payment_status')->default('unpaid')->after('cost');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->text('payment_notes')->nullable()->after('paid_at');
        });

        // Restore payment data from charges
        $charges = DB::table('charges')
            ->where('chargeable_type', RehearsalReservation::class)
            ->get();

        foreach ($charges as $charge) {
            DB::table('reservations')
                ->where('id', $charge->chargeable_id)
                ->update([
                    'cost' => $charge->net_amount / 100,
                    'payment_status' => $this->mapChargeStatusToPaymentStatus($charge->status),
                    'payment_method' => $charge->payment_method,
                    'paid_at' => $charge->paid_at,
                    'payment_notes' => $charge->notes,
                ]);
        }
    }

    /**
     * Map charge status string back to legacy payment_status string.
     */
    protected function mapChargeStatusToPaymentStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'paid',
            'comped' => 'comped',
            'refunded' => 'refunded',
            'pending' => 'unpaid',
            default => 'unpaid',
        };
    }
};
