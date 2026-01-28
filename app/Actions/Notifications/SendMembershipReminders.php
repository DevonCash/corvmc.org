<?php

namespace App\Actions\Notifications;

use App\Exceptions\Services\NotificationSchedulingException;
use App\Models\User;
use CorvMC\Finance\Notifications\MembershipReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMembershipReminders
{
    use AsAction;

    /**
     * Send membership reminders to users who haven't been active recently.
     */
    public function handle(bool $dryRun = false, int $inactiveDays = 90): array
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
                    return ! $user->isSustainingMember();
                });

            $results = [
                'total' => $inactiveUsers->count(),
                'sent' => 0,
                'failed' => 0,
                'errors' => [],
                'users' => [],
            ];

            foreach ($inactiveUsers as $user) {
                /** @var \CorvMC\SpaceManagement\Models\Reservation|null $lastReservation */
                $lastReservation = $user->reservations()->latest()->first();
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'last_reservation' => $lastReservation?->created_at,
                    'status' => 'pending',
                ];

                if (! $dryRun) {
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
}
