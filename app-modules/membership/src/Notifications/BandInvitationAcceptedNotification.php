<?php

namespace CorvMC\Membership\Notifications;

use CorvMC\Membership\Models\Band;
use CorvMC\Membership\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BandInvitationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Band $band,
        public User $newMember
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
            ->subject("New member joined {$this->band->name}!")
            ->greeting('Hello!')
            ->line("{$this->newMember->name} has accepted the invitation to join {$this->band->name}!")
            ->line('You can now collaborate and make music together.')
            ->action('View Band Profile', route('bands.show', $this->band))
            ->line('Welcome your new band member!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New Band Member',
            'body' => "{$this->newMember->name} has joined {$this->band->name}",
            'icon' => 'tabler-user-plus',
            'band_id' => $this->band->id,
            'band_name' => $this->band->name,
            'new_member_id' => $this->newMember->id,
            'new_member_name' => $this->newMember->name,
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
            'new_member_id' => $this->newMember->id,
            'new_member_name' => $this->newMember->name,
        ];
    }
}
