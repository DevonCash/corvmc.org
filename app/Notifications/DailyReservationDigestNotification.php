<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyReservationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var Collection<int, \App\Models\Reservation>
     */
    public Collection $reservations;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection $reservations)
    {
        $this->reservations = $reservations;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $date = now()->format('l, F j, Y');
        $count = $this->reservations->count();
        $shortDate = now()->format('M j');

        $message = (new MailMessage)
            ->subject("{$shortDate}: {$count} ".str('reservation')->plural($count));

        if ($count === 0) {
            return $message
                ->line("**No reservations scheduled for today** ({$date}).")
                ->line('The practice space is available all day.');
        }

        $message->line("**{$count} ".str('reservation')->plural($count)." scheduled for today** ({$date}):");

        foreach ($this->reservations as $reservation) {
            $user = $reservation->getResponsibleUser();
            $startTime = $reservation->reserved_at->format('g:i A');
            $endTime = $reservation->reserved_until->format('g:i A');
            $duration = $reservation->duration;
            $status = ucfirst($reservation->status);

            $message->line('---');
            $message->line("**{$startTime} - {$endTime}** ({$duration} hours)");
            $message->line("Member: {$user->name}");
            $message->line("Status: {$status}");

            if ($reservation->notes) {
                $message->line("Notes: {$reservation->notes}");
            }
        }

        return $message
            ->line('---')
            ->action('View All Reservations', url('/member/rehearsal-reservations'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'date' => now()->toDateString(),
            'count' => $this->reservations->count(),
            'reservations' => $this->reservations->map(fn ($r) => [
                'id' => $r->id,
                'user' => $r->getResponsibleUser()->name,
                'start' => $r->reserved_at,
                'end' => $r->reserved_until,
            ]),
        ];
    }
}
