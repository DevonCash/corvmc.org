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
            ->line('Your practice space reservation is coming up in 3 days!')
            ->line('**Reservation Details:**')
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->hours_used} hours")
            ->line('Cost: '.$this->reservation->cost_display)
            ->line('')
            ->line('**Action Required:** Please confirm this reservation to let us know you remember it, or cancel if you can\'t make it. If no action is taken within 24 hours, it will be automatically cancelled to make the slot available to other members.')
            ->line('')
            ->line('Note: Your time slot and credits are already locked in. Confirmation is just an acknowledgement that you haven\'t forgotten.')
            ->line('')
            ->action('View Reservation', route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]))
            ->line('From there you can confirm or cancel your reservation.')
            ->line('Thank you for using the Corvallis Music Collective!')
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Action Required: Upcoming Reservation',
            'message' => "Your practice space reservation for {$this->reservation->time_range} is coming up soon. Please confirm or cancel within 24 hours.",
            'action_url' => route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]),
            'action_text' => 'View Reservation',
            'reservation_id' => $this->reservation->id,
            'days_until_reservation' => now()->diffInDays($this->reservation->reserved_at),
        ];
    }
}
