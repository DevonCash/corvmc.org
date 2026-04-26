<?php

namespace CorvMC\SpaceManagement\Notifications;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\Support\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RehearsalAttendanceRequestedNotification extends Notification
{
    use Queueable;

    public RehearsalReservation $reservation;

    public string $date;

    public string $time;

    public string $bookerName;

    public function __construct(
        public Invitation $invitation,
    ) {
        $this->reservation = $invitation->invitable;
        $this->date = $this->reservation->reserved_at->format('l, M j, Y');
        $this->time = $this->reservation->reserved_at->format('g:i A')
            .' – '.$this->reservation->reserved_until->format('g:i A');
        $this->bookerName = $this->reservation->reservable?->name ?? 'Someone';
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
            ->subject("Rehearsal on {$this->date} — can you make it?")
            ->greeting('Hello!')
            ->line("{$this->bookerName} has a rehearsal booked and wants to know if you can make it.")
            ->line("**When:** {$this->date}")
            ->line("**Time:** {$this->time}")
            ->action('Respond', url('/member'))
            ->line('Let them know if you can make it!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => 'Rehearsal Attendance',
            'body' => "{$this->bookerName} has a rehearsal on {$this->date} at {$this->time}. Can you make it?",
            'icon' => 'tabler-metronome',
            'invitation_id' => $this->invitation->id,
            'reservation_id' => $this->reservation->id,
        ];
    }

    /**
     * Get the array representation of the notification (fallback).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'reservation_id' => $this->reservation->id,
        ];
    }
}
