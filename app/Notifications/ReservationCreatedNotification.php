<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationCreatedNotification extends Notification implements ShouldQueue
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
        $subject = $this->reservation->status === 'pending'
            ? 'Practice Space Reservation Request Received'
            : 'Practice Space Reservation Created';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!");

        if ($this->reservation->status === 'pending') {
            $message->line('We\'ve received your practice space reservation request.')
                ->line('Your reservation is currently **pending** and needs your confirmation.')
                ->line('We\'ll send you a confirmation reminder 3 days before your reservation date.')
                ->line('You\'ll need to confirm the reservation at that time to secure your slot.');
        } else {
            $message->line('Your practice space reservation has been created.');
        }

        $message->line("**Reservation Details:**")
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->hours_used} hours")
            ->line("Cost: " . $this->reservation->cost_display);

        if ($this->reservation->free_hours_used > 0) {
            $message->line("Free Hours Used: {$this->reservation->free_hours_used} hours");
        }

        if ($this->reservation->notes) {
            $message->line("Notes: {$this->reservation->notes}");
        }

        $message->action('View Reservations', route('filament.member.pages.dashboard'))
            ->line('Thank you for using the Corvallis Music Collective!')
            ->salutation('The CMC Team');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->reservation->status === 'pending'
                ? 'Reservation Request Received'
                : 'Reservation Created',
            'message' => $this->reservation->status === 'pending'
                ? "Practice space reservation request for {$this->reservation->time_range} received. We'll send a confirmation reminder 3 days before."
                : "Practice space reservation for {$this->reservation->time_range} has been created.",
            'action_url' => route('filament.member.pages.dashboard'),
            'action_text' => 'View Reservations',
            'reservation_id' => $this->reservation->id,
            'status' => $this->reservation->status,
        ];
    }
}
