<?php

namespace App\Notifications;

use CorvMC\SpaceManagement\Models\Reservation;
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
        $subject = $this->reservation->status->isScheduled()
            ? 'Practice Space Reservation Scheduled'
            : 'Practice Space Reservation Confirmed';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!");

        if ($this->reservation->status->isScheduled()) {
            $message->line('Your practice space reservation has been scheduled!')
                ->line('Your time slot and credits have been locked in.')
                ->line('We\'ll send you a confirmation reminder 3 days before your reservation date.')
                ->line('Please confirm at that time to let us know you remember your reservation.');
        } else {
            $message->line('Your practice space reservation has been confirmed.');
        }

        $message->line('**Reservation Details:**')
            ->line("Date & Time: {$this->reservation->time_range}")
            ->line("Duration: {$this->reservation->hours_used} hours")
            ->line('Cost: '.$this->reservation->cost_display);

        if ($this->reservation->free_hours_used > 0) {
            $message->line("Free Hours Used: {$this->reservation->free_hours_used} hours");
        }

        if ($this->reservation->notes) {
            $message->line("Notes: {$this->reservation->notes}");
        }

        $message->action('View Reservation', route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]))
            ->line('Thank you for using the Corvallis Music Collective!')
            ->salutation('The CMC Team');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->reservation->status->isScheduled()
                ? 'Reservation Scheduled'
                : 'Reservation Confirmed',
            'message' => $this->reservation->status->isScheduled()
                ? "Practice space reservation for {$this->reservation->time_range} has been scheduled. We'll send a confirmation reminder 3 days before."
                : "Practice space reservation for {$this->reservation->time_range} has been confirmed.",
            'action_url' => route('filament.member.resources.reservations.index', ['view' => $this->reservation->id]),
            'action_text' => 'View Reservations',
            'reservation_id' => $this->reservation->id,
            'status' => $this->reservation->status->value,
        ];
    }
}
