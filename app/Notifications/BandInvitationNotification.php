<?php

namespace App\Notifications;

use App\Models\Band;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BandInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Band $band,
        public string $role,
        public ?string $position = null
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
    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject("You're invited to join {$this->band->name}!")
            ->greeting('Hello!')
            ->line("You've been invited to join {$this->band->name}" . ($this->position ? " as {$this->position}" : '') . '.')
            ->line("Here's a bit about the band:")
            ->line($this->band->bio ?: 'No bio available yet.')
            ->action('View Band Profile', route('bands.show', $this->band))
            ->line('You can accept or decline this invitation from your dashboard.')
            ->line('Welcome to the music community!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Band Invitation',
            'body' => "You've been invited to join {$this->band->name}".($this->position ? " as {$this->position}" : ''),
            'icon' => 'heroicon-o-user-group',
            'band_id' => $this->band->id,
            'band_name' => $this->band->name,
            'role' => $this->role,
            'position' => $this->position,
        ];
    }

    /**
     * Get the array representation of the notification (fallback).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'band_id' => $this->band->id,
            'band_name' => $this->band->name,
            'role' => $this->role,
            'position' => $this->position,
        ];
    }
}
