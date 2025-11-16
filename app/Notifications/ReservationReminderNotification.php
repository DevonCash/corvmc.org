<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationReminderNotification extends Notification implements ShouldQueue
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
            ->subject('Practice Space Reminder - Tomorrow!')
            ->greeting('Hello!')
            ->line('This is a friendly reminder about your upcoming practice space reservation.')
            ->line('**Reservation Details:**')
            ->line('Time: '.$this->reservation->time_range)
            ->line('Duration: '.number_format($this->reservation->duration, 1).' hours')
            ->line('Cost: '.$this->reservation->cost_display);

        if ($this->reservation->isUnpaid() && ! $this->reservation->cost->isZero()) {
            $message->line('**Payment Due:** Please bring payment or pay online before your session.');
        }

        if ($this->reservation->notes) {
            $message->line('**Notes:** '.$this->reservation->notes);
        }

        return $message
            ->line('**Address:** Corvallis Music Collective, [ADDRESS]')
            ->line('**What to bring:** Your instruments, cables, and any equipment you need')
            ->action('View Reservation', route('filament.member.resources.reservations.view', $this->reservation))
            ->line('Need to reschedule? Contact us ASAP.')
            ->line('See you soon!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Practice Space Reminder',
            'body' => 'Your practice space reservation is tomorrow at '.$this->reservation->reserved_at->format('g:i A').'.',
            'icon' => 'tabler-clock',
            'reservation_id' => $this->reservation->id,
            'reserved_at' => $this->reservation->reserved_at,
            'duration' => $this->reservation->duration,
            'cost' => $this->reservation->cost->getMinorAmount()->toInt(),
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
            'cost' => $this->reservation->cost->getMinorAmount()->toInt(),
        ];
    }
}
