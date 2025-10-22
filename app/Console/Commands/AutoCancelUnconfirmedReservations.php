<?php

namespace App\Console\Commands;

use App\Actions\Reservations\CancelReservation;
use App\Models\RehearsalReservation;
use Illuminate\Console\Command;

class AutoCancelUnconfirmedReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-cancel pending reservations that missed their confirmation window';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding unconfirmed reservations past their confirmation deadline...');

        // Find pending rehearsal reservations that are now within 3 days
        // (they should have been confirmed by now)
        $threeDaysFromNow = now()->addDays(3);

        $unconfirmedReservations = RehearsalReservation::where('status', 'pending')
            ->where('reserved_at', '<=', $threeDaysFromNow)
            ->where('reserved_at', '>', now()) // Don't cancel past reservations
            ->get();

        $cancelledCount = 0;

        foreach ($unconfirmedReservations as $reservation) {
            if ($reservation->shouldAutoCancel()) {
                try {
                    CancelReservation::run($reservation, 'auto_cancelled', [
                        'cancellation_reason' => 'Reservation not confirmed within required window (3-7 days before)',
                    ]);

                    $this->line("Cancelled reservation #{$reservation->id} for {$reservation->getResponsibleUser()->name}");
                    $cancelledCount++;
                } catch (\Exception $e) {
                    $this->error("Failed to cancel reservation #{$reservation->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Auto-cancelled {$cancelledCount} unconfirmed reservation(s).");

        return Command::SUCCESS;
    }
}
