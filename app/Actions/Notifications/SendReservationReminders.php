<?php

namespace App\Actions\Notifications;

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SendReservationReminders
{
    use AsAction;

    /**
     * Send reservation reminders for upcoming reservations.
     */
    public function handle(bool $dryRun = false): array
    {
        $tomorrow = Carbon::now()->addDay();
        $startOfTomorrow = $tomorrow->copy()->startOfDay();
        $endOfTomorrow = $tomorrow->copy()->endOfDay();

        $reservations = Reservation::with('reservable')
            ->where('status', 'confirmed')
            ->whereBetween('reserved_at', [$startOfTomorrow, $endOfTomorrow])
            ->get();

        $results = [
            'total' => $reservations->count(),
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
            'reservations' => [],
        ];

        foreach ($reservations as $reservation) {
            $reservable = $reservation->reservable;
            $reservationData = [
                'id' => $reservation->id,
                'user_name' => $reservable instanceof User ? $reservable->name : null,
                'user_email' => $reservable instanceof User ? $reservable->email : null,
                'time_range' => $reservation->time_range,
                'status' => 'pending',
            ];

            if (! $dryRun && $reservable instanceof User) {
                try {
                    $reservable->notify(new ReservationReminderNotification($reservation));
                    $reservationData['status'] = 'sent';
                    $results['sent']++;

                    Log::info('Reservation reminder sent', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservable->email,
                    ]);
                } catch (\Exception $e) {
                    $reservationData['status'] = 'failed';
                    $reservationData['error'] = $e->getMessage();
                    $results['failed']++;
                    $results['errors'][] = "Reservation {$reservation->id}: {$e->getMessage()}";

                    Log::error('Failed to send reservation reminder', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservable->email,
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
