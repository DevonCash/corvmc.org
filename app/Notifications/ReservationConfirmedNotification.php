<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmedNotification extends Notification implements ShouldQueue
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
        $message = (new MailMessage)
            ->subject('Practice Space Reservation Confirmed')
            ->greeting('Hello!')
            ->line('Your practice space reservation has been confirmed!')
            ->line('**Reservation Details:**')
            ->line('Time: '.$this->reservation->time_range)
            ->line('Duration: '.number_format($this->reservation->duration, 1).' hours')
            ->line('Cost: '.$this->reservation->cost_display);

        if ($this->reservation->notes) {
            $message->line('**Notes:** '.$this->reservation->notes);
        }

        if ($this->reservation->isUnpaid() && ! $this->reservation->cost->isZero()) {
            $message->line('**Payment Required:** Please bring payment or pay online before your session.');
        }

        return $message
            ->action('View Reservation', route('filament.member.resources.reservations.view', $this->reservation))
            ->line('We look forward to seeing you at the practice space!')
            ->line('If you need to make changes, please contact us at least 24 hours in advance.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Reservation Confirmed',
            'body' => 'Your practice space reservation for '.$this->reservation->reserved_at->format('M j, Y g:i A').' has been confirmed.',
            'icon' => 'tabler-clock-check',
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
