<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationAutoCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Recurring Reservation Auto-Cancelled')
            ->greeting('Hello!')
            ->line('Your recurring practice space reservation was automatically cancelled because it was not confirmed within the 3-day window.')
            ->line('**Cancelled Reservation Details:**')
            ->line('Time: '.$this->reservation->time_range)
            ->line('Duration: '.number_format($this->reservation->duration, 1).' hours')
            ->line('**Why was this cancelled?**')
            ->line('Recurring reservations must be confirmed at least 3 days before the scheduled time. This gives you the right of first refusal for your regular time slot while ensuring the space is available to others if you can\'t make it.')
            ->line('**Your credits were not deducted** for this reservation since it wasn\'t confirmed.')
            ->action('View Reservations', route('filament.member.resources.reservations.index'))
            ->line('If you want to keep your recurring time slot, please remember to confirm your future reservations when you receive the reminder email.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Reservation Auto-Cancelled',
            'body' => 'Your recurring reservation for '.$this->reservation->reserved_at->format('M j, Y g:i A').' was auto-cancelled. Please confirm future reservations within 3 days.',
            'icon' => 'tabler-calendar-cancel',
            'reservation_id' => $this->reservation->id,
            'reserved_at' => $this->reservation->reserved_at,
            'duration' => $this->reservation->duration,
            'cost' => $this->reservation->cost,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'reserved_at' => $this->reservation->reserved_at,
            'duration' => $this->reservation->duration,
            'cost' => $this->reservation->cost,
        ];
    }
}
