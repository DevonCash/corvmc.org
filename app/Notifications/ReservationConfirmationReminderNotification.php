<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationConfirmationReminderNotification extends Notification implements ShouldQueue
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
            ->subject('Action Required: Confirm Your Practice Space Reservation')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Your practice space reservation is coming up in 3 days and needs your confirmation.')
            ->line('**Reservation Details:**')
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->hours_used} hours")
            ->line('Cost: $'.number_format($this->reservation->cost, 2))
            ->line('')
            ->line('**Action Required:** Please confirm this reservation within 24 hours or it will be automatically cancelled to make the slot available to other members.')
            ->line('')
            ->action('Confirm Reservation', url('/member'))
            ->line('If you need to cancel or make changes to this reservation, you can do so from your member dashboard.')
            ->line('Thank you for using the Corvallis Music Collective!')
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Action Required: Confirm Reservation',
            'message' => "Your practice space reservation for {$this->reservation->time_range} needs confirmation within 24 hours or it will be automatically cancelled.",
            'action_url' => url('/member'),
            'action_text' => 'Confirm Reservation',
            'reservation_id' => $this->reservation->id,
            'days_until_reservation' => now()->diffInDays($this->reservation->reserved_at),
        ];
    }
}
