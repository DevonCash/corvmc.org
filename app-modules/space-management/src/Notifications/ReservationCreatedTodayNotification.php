<?php

namespace CorvMC\SpaceManagement\Notifications;

use CorvMC\SpaceManagement\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationCreatedTodayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Reservation $reservation)
    {
        //
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
        $user = $this->reservation->getResponsibleUser();
        $startTime = $this->reservation->reserved_at->format('g:i A');
        $endTime = $this->reservation->reserved_until->format('g:i A');
        $duration = $this->reservation->duration;

        return (new MailMessage)
            ->subject('Reservation Created for Today')
            ->line('A new reservation has been created for **today**.')
            ->line("**Member:** {$user->name}")
            ->line("**Time:** {$startTime} - {$endTime} ({$duration} hours)")
            ->line('**Status:** '.ucfirst($this->reservation->status))
            ->when($this->reservation->cost && ! $this->reservation->cost->isZero(), function ($message) {
                return $message->line("**Cost:** {$this->reservation->cost->formatTo('en_US')}");
            })
            ->when($this->reservation->notes, function ($message) {
                return $message->line("**Notes:** {$this->reservation->notes}");
            })
            ->action('View Reservation', url("/member/rehearsal-reservations/{$this->reservation->id}"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'user_name' => $this->reservation->getResponsibleUser()->name,
            'start_time' => $this->reservation->reserved_at,
            'end_time' => $this->reservation->reserved_until,
        ];
    }
}
