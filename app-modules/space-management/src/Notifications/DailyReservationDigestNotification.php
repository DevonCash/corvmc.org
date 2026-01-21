<?php

namespace CorvMC\SpaceManagement\Notifications;

use Carbon\Carbon;
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

    public Carbon $date;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection $reservations, Carbon $date)
    {
        $this->reservations = $reservations;
        $this->date = $date;
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
        $date = $this->date->format('l, F j, Y');
        $count = $this->reservations->count();
        $shortDate = $this->date->format('M j');

        $message = (new MailMessage)
            ->subject("{$shortDate}: {$count} ".str('reservation')->plural($count));

        if ($count === 0) {
            return $message
                ->line("**No reservations scheduled for tomorrow** ({$date}).")
                ->line('The practice space is available all day.');
        }

        $message->line("**{$count} ".str('reservation')->plural($count)." scheduled for tomorrow** ({$date}):");

        foreach ($this->reservations as $reservation) {
            $user = $reservation->getResponsibleUser();
            $startTime = $reservation->reserved_at->format('g:i A');
            $endTime = $reservation->reserved_until->format('g:i A');
            $duration = $reservation->duration;
            $status = ucfirst($reservation->status->name);

            $message->line('---');
            $message->line("**{$startTime} - {$endTime}** ({$duration} hours)");

            $memberLine = "Member: {$user->name}";
            if ($reservation->isFirstReservationForUser()) {
                $memberLine .= ' ⭐ First reservation!';
            }
            $message->line($memberLine);

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
            'date' => $this->date->toDateString(),
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
