<?php

namespace App\Services;

use App\Exceptions\Services\NotificationSchedulingException;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationReminderNotification;
use App\Notifications\ReservationConfirmationNotification;
use App\Notifications\MembershipReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;

class NotificationSchedulingService
{
    /**
     * Send reservation reminders for upcoming reservations.
     */
    public function sendReservationReminders(bool $dryRun = false): array
    {
        $tomorrow = Carbon::now()->addDay();
        $startOfTomorrow = $tomorrow->copy()->startOfDay();
        $endOfTomorrow = $tomorrow->copy()->endOfDay();

        $reservations = $this->getUpcomingReservations($startOfTomorrow, $endOfTomorrow);

        $results = [
            'total' => $reservations->count(),
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
            'reservations' => [],
        ];

        foreach ($reservations as $reservation) {
            $reservationData = [
                'id' => $reservation->id,
                'user_name' => $reservation->user->name,
                'user_email' => $reservation->user->email,
                'time_range' => $reservation->time_range,
                'status' => 'pending',
            ];

            if (!$dryRun) {
                try {
                    $reservation->user->notify(new ReservationReminderNotification($reservation));
                    $reservationData['status'] = 'sent';
                    $results['sent']++;

                    Log::info('Reservation reminder sent', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservation->user->email,
                    ]);
                } catch (\Exception $e) {
                    $reservationData['status'] = 'failed';
                    $reservationData['error'] = $e->getMessage();
                    $results['failed']++;
                    $results['errors'][] = "Reservation {$reservation->id}: {$e->getMessage()}";

                    Log::error('Failed to send reservation reminder', [
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

    /**
     * Send reservation confirmation reminders for pending reservations.
     */
    public function sendReservationConfirmationReminders(bool $dryRun = false): array
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

    /**
     * Send membership reminders to users who haven't been active recently.
     */
    public function sendMembershipReminders(bool $dryRun = false, int $inactiveDays = 90): array
    {
        if ($inactiveDays <= 0) {
            throw NotificationSchedulingException::invalidInactiveDaysValue($inactiveDays);
        }

        try {
            $cutoffDate = Carbon::now()->subDays($inactiveDays);

        // Find users who:
        // - Have not made a reservation recently
        // - Are not sustaining members
        // - Have email verified (active account)
        $inactiveUsers = User::whereNotNull('email_verified_at')
            ->whereDoesntHave('reservations', function ($query) use ($cutoffDate) {
                $query->where('created_at', '>', $cutoffDate);
            })
            ->get()
            ->filter(function ($user) {
                return !$user->isSustainingMember();
            });

        $results = [
            'total' => $inactiveUsers->count(),
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
            'users' => [],
        ];

        foreach ($inactiveUsers as $user) {
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_reservation' => $user->reservations()->latest()->first()?->created_at,
                'status' => 'pending',
            ];

            if (!$dryRun) {
                try {
                    $user->notify(new MembershipReminderNotification($user));
                    $userData['status'] = 'sent';
                    $results['sent']++;

                    Log::info('Membership reminder sent', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                    ]);
                } catch (\Exception $e) {
                    $userData['status'] = 'failed';
                    $userData['error'] = $e->getMessage();
                    $results['failed']++;
                    $results['errors'][] = "User {$user->id}: {$e->getMessage()}";

                    Log::error('Failed to send membership reminder', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $userData['status'] = 'dry_run';
            }

            $results['users'][] = $userData;
        }

            return $results;
        } catch (\Exception $e) {
            if ($e instanceof NotificationSchedulingException) {
                throw $e;
            }
            throw new NotificationSchedulingException("Failed to send membership reminders: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get upcoming reservations that need reminders.
     */
    private function getUpcomingReservations(Carbon $startDate, Carbon $endDate): Collection
    {
        return Reservation::with('user')
            ->where('status', 'confirmed')
            ->whereBetween('reserved_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * Get statistics about notification sending.
     */
    public function getNotificationStats(): array
    {
        $today = Carbon::now()->startOfDay();
        $tomorrow = Carbon::now()->addDay();

        return [
            'reservations_tomorrow' => $this->getUpcomingReservations(
                $tomorrow->copy()->startOfDay(),
                $tomorrow->copy()->endOfDay()
            )->count(),
            'pending_reservations' => Reservation::where('status', 'pending')
                ->where('created_at', '<=', Carbon::now()->subDay())
                ->where('reserved_at', '>', Carbon::now())
                ->count(),
            'inactive_users' => User::whereNotNull('email_verified_at')
                ->whereDoesntHave('reservations', function ($query) {
                    $query->where('created_at', '>', Carbon::now()->subDays(90));
                })
                ->get()
                ->filter(function ($user) {
                    return !$user->isSustainingMember();
                })
                ->count(),
        ];
    }

    /**
     * Schedule a custom notification for a specific user.
     */
    public function scheduleCustomNotification(User $user, $notification, Carbon $sendAt = null): bool
    {
        if (!$notification instanceof Notification) {
            throw NotificationSchedulingException::invalidNotificationClass(get_class($notification));
        }

        if (!$user->exists) {
            throw NotificationSchedulingException::userNotFound($user->id ?? 0);
        }

        if ($sendAt && $sendAt->isPast()) {
            throw NotificationSchedulingException::invalidSchedulingDate($sendAt);
        }

        try {
            if ($sendAt && $sendAt->isFuture()) {
                // Would need to integrate with Laravel queue scheduling for future notifications
                // For now, just send immediately if no future date specified
                Log::info('Custom notification scheduled for future delivery', [
                    'user_id' => $user->id,
                    'notification_class' => get_class($notification),
                    'send_at' => $sendAt,
                ]);

                // TODO: Implement queue job for future delivery
                return true;
            } else {
                $user->notify($notification);
                Log::info('Custom notification sent immediately', [
                    'user_id' => $user->id,
                    'notification_class' => get_class($notification),
                ]);
                return true;
            }
        } catch (\Exception $e) {
            $errorMessage = "Failed to schedule notification for user {$user->id}: {$e->getMessage()}";
            Log::error($errorMessage, [
                'user_id' => $user->id,
                'notification_class' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
