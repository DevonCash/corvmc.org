<?php

namespace App\Actions\Notifications;

use App\Models\Reservation;
use App\Notifications\ReservationConfirmationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SendReservationConfirmationReminders
{
    use AsAction;

    /**
     * Send reservation confirmation reminders for pending reservations.
     */
    public function handle(bool $dryRun = false): array
    {
        // Find reservations that are pending and created more than 24 hours ago
        $cutoffDate = Carbon::now()->subDay();

        $pendingReservations = Reservation::with('user')
            ->where('status', 'pending')
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
            $reservationData = [
                'id' => $reservation->id,
                'user_name' => $reservation->user->name,
                'user_email' => $reservation->user->email,
                'time_range' => $reservation->time_range,
                'created_at' => $reservation->created_at,
                'status' => 'pending',
            ];

            if (!$dryRun) {
                try {
                    $reservation->user->notify(new ReservationConfirmationNotification($reservation));
                    $reservationData['status'] = 'sent';
                    $results['sent']++;

                    Log::info('Reservation confirmation reminder sent', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservation->user->email,
                    ]);
                } catch (\Exception $e) {
                    $reservationData['status'] = 'failed';
                    $reservationData['error'] = $e->getMessage();
                    $results['failed']++;
                    $results['errors'][] = "Reservation {$reservation->id}: {$e->getMessage()}";

                    Log::error('Failed to send reservation confirmation reminder', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservation->user->email,
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
