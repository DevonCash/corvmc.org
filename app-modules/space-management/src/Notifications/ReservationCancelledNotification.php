<?php

namespace CorvMC\SpaceManagement\Notifications;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Practice Space Reservation Cancelled')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Your practice space reservation has been cancelled.')
            ->line('**Cancelled Reservation Details:**')
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->hours_used} hours")
            ->line('If this cancellation was unexpected, please contact us immediately.')
            ->action('View Reservations', route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]))
            ->line('You can make a new reservation anytime from your dashboard.')
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reservation Cancelled',
            'message' => "Your practice space reservation for {$this->reservation->time_range} has been cancelled.",
            'action_url' => route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]),
            'action_text' => 'View Reservations',
            'reservation_id' => $this->reservation->id,
        ];
    }
}
