<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm Your Practice Space Reservation')
            ->greeting("Hi {$notifiable->name}!")
            ->line('You have a pending practice space reservation that needs confirmation.')
            ->line('**Reservation Details:**')
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->duration} hours")
            ->line("Cost: \${$this->reservation->cost}")
            ->action('Confirm Reservation', route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]))
            ->line('Please confirm your reservation as soon as possible to secure your time slot.')
            ->line('If you need to make changes or cancel, you can do so from your dashboard.')
            ->salutation('Thanks for being part of the Corvallis Music Collective!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'time_range' => $this->reservation->time_range,
            'cost' => $this->reservation->cost,
        ];
    }
}
