<?php

namespace App\Actions\Notifications;

use CorvMC\SpaceManagement\Models\Reservation;
use App\Notifications\ReservationConfirmationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SendReservationConfirmationReminders
{
    use AsAction;

    /**
     * Send reservation confirmation reminders for scheduled reservations.
     */
    public function handle(bool $dryRun = false): array
    {
        // Find reservations that are scheduled and created more than 24 hours ago
        $cutoffDate = Carbon::now()->subDay();

        $pendingReservations = Reservation::query()
            ->with('reservable')
            ->status(\CorvMC\SpaceManagement\Enums\ReservationStatus::Scheduled)
            ->where('created_at', '<=', $cutoffDate)
            ->where('reserved_at', '>', Carbon::now()) // Only future reservations
            ->get();

        $results = [
            'total' => $pendingReservations->count(),
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
            'reservations' => [],
        ];

        foreach ($pendingReservations as $reservation) {
            $responsibleUser = $reservation->getResponsibleUser();

            $reservationData = [
                'id' => $reservation->id,
                'user_name' => $responsibleUser?->name,
                'user_email' => $responsibleUser?->email,
                'time_range' => $reservation->time_range,
                'created_at' => $reservation->created_at,
                'status' => 'pending',
            ];

            if (! $dryRun) {
                try {
                    $reservation->getResponsibleUser()?->notify(new ReservationConfirmationNotification($reservation));
                    $reservationData['status'] = 'sent';
                    $results['sent']++;

                    Log::info('Reservation confirmation reminder sent', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $responsibleUser?->email,
                    ]);
                } catch (\Exception $e) {
                    $reservationData['status'] = 'failed';
                    $reservationData['error'] = $e->getMessage();
                    $results['failed']++;
                    $results['errors'][] = "Reservation {$reservation->id}: {$e->getMessage()}";

                    Log::error('Failed to send reservation confirmation reminder', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $responsibleUser?->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $reservationData['status'] = 'dry_run';
            }

            $results['reservations'][] = $reservationData;
        }

        return $results;
    }
}
