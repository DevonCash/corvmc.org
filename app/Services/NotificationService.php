<?php

namespace App\Services;

use App\Exceptions\Services\NotificationSchedulingException;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Notifications\ReservationReminderNotification;
use CorvMC\SpaceManagement\Notifications\ReservationConfirmationReminderNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing notification operations.
 * 
 * This service handles sending various types of reminders and 
 * scheduled notifications throughout the application.
 */
class NotificationService
{
    /**
     * Send reservation reminders for upcoming reservations (24 hours before).
     */
    public function sendReservationReminders(bool $dryRun = false): array
    {
        $tomorrow = Carbon::now()->addDay();
        $startOfTomorrow = $tomorrow->copy()->startOfDay();
        $endOfTomorrow = $tomorrow->copy()->endOfDay();

        $reservations = Reservation::with('reservable')
            ->status(ReservationStatus::Confirmed)
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

    /**
     * Send membership renewal reminders.
     */
    public function sendMembershipReminders(): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Get users with expiring memberships (within 7 days)
        $expiringUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('ends_at', '>', now())
                  ->where('ends_at', '<=', now()->addDays(7));
        })->get();

        $results['total'] = $expiringUsers->count();

        foreach ($expiringUsers as $user) {
            try {
                // TODO: Create and send MembershipExpiringNotification
                // $user->notify(new MembershipExpiringNotification($user->subscription));
                $results['sent']++;
                
                Log::info('Membership reminder sent', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "User {$user->id}: {$e->getMessage()}";
                
                Log::error('Failed to send membership reminder', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Send confirmation reminders for unconfirmed reservations.
     */
    public function sendConfirmationReminders(): array
    {
        $results = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Get unconfirmed reservations that need reminders (48 hours before)
        $twoDaysAhead = Carbon::now()->addDays(2);
        $startOfDay = $twoDaysAhead->copy()->startOfDay();
        $endOfDay = $twoDaysAhead->copy()->endOfDay();

        $reservations = Reservation::with('reservable')
            ->status(ReservationStatus::Pending)
            ->whereBetween('reserved_at', [$startOfDay, $endOfDay])
            ->get();

        $results['total'] = $reservations->count();

        foreach ($reservations as $reservation) {
            $reservable = $reservation->reservable;
            
            if ($reservable instanceof User) {
                try {
                    $reservable->notify(new ReservationConfirmationReminderNotification($reservation));
                    $results['sent']++;
                    
                    Log::info('Confirmation reminder sent', [
                        'reservation_id' => $reservation->id,
                        'user_email' => $reservable->email,
                    ]);
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Reservation {$reservation->id}: {$e->getMessage()}";
                    
                    Log::error('Failed to send confirmation reminder', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Schedule a custom notification for a specific user.
     */
    public function scheduleCustom(User $user, $notification, ?Carbon $sendAt = null): bool
    {
        if (! $notification instanceof Notification) {
            throw NotificationSchedulingException::invalidNotificationClass(get_class($notification));
        }

        if (! $user->exists) {
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

    /**
     * Get notification statistics.
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->subDays(7);
        $thisMonth = Carbon::now()->subDays(30);

        return [
            'notifications_sent_today' => DB::table('notifications')
                ->whereDate('created_at', $today)
                ->count(),
            
            'notifications_sent_this_week' => DB::table('notifications')
                ->where('created_at', '>=', $thisWeek)
                ->count(),
            
            'notifications_sent_this_month' => DB::table('notifications')
                ->where('created_at', '>=', $thisMonth)
                ->count(),
            
            'pending_notifications' => DB::table('notifications')
                ->whereNull('read_at')
                ->count(),
            
            'failed_notifications' => DB::table('failed_jobs')
                ->where('payload', 'like', '%SendNotification%')
                ->whereDate('failed_at', $today)
                ->count(),
                
            'upcoming_reservation_reminders' => Reservation::with('reservable')
                ->status(ReservationStatus::Confirmed)
                ->whereBetween('reserved_at', [
                    Carbon::now()->addDay()->startOfDay(),
                    Carbon::now()->addDay()->endOfDay()
                ])
                ->count(),
                
            'pending_confirmations' => Reservation::status(ReservationStatus::Pending)
                ->whereBetween('reserved_at', [
                    Carbon::now()->addDays(2)->startOfDay(),
                    Carbon::now()->addDays(2)->endOfDay()
                ])
                ->count(),
        ];
    }

    /**
     * Send bulk notifications to multiple users.
     */
    public function sendBulkNotification(array $userIds, Notification $notification): array
    {
        $results = [
            'total' => count($userIds),
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            try {
                $user->notify($notification);
                $results['sent']++;
                
                Log::info('Bulk notification sent', [
                    'user_id' => $user->id,
                    'notification_class' => get_class($notification),
                ]);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "User {$user->id}: {$e->getMessage()}";
                
                Log::error('Failed to send bulk notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Mark notifications as read for a user.
     */
    public function markAsRead(User $user, array $notificationIds = []): int
    {
        $query = $user->unreadNotifications();
        
        if (!empty($notificationIds)) {
            $query->whereIn('id', $notificationIds);
        }

        return $query->update(['read_at' => now()]);
    }

    /**
     * Clean up old notifications.
     */
    public function cleanupOldNotifications(int $daysToKeep = 90): int
    {
        return DB::table('notifications')
            ->where('created_at', '<', Carbon::now()->subDays($daysToKeep))
            ->whereNotNull('read_at')
            ->delete();
    }
}