<?php

namespace CorvMC\Membership\Notifications;

use CorvMC\Bands\Models\Band;
use CorvMC\Support\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BandInvitationNotification extends Notification
{
    use Queueable;

    public Band $band;

    public string $role;

    public ?string $position;

    public function __construct(
        public Invitation $invitation,
    ) {
        $this->band = $invitation->invitable;
        $this->role = $invitation->data['role'] ?? 'member';
        $this->position = $invitation->data['position'] ?? null;
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
        $roleLabel = $this->role === 'admin' ? 'an admin' : 'a member';
        $acceptUrl = url("/band/{$this->band->slug}/accept-invitation");

        $message = (new MailMessage)
            ->subject("You're invited to join {$this->band->name} as {$roleLabel}!")
            ->greeting('Hello!')
            ->line("You've been invited to join {$this->band->name} as {$roleLabel}".($this->position ? " ({$this->position})" : '').'.');

        if ($this->band->bio) {
            $message->line("Here's a bit about the band:")
                ->line($this->band->bio);
        }

        return $message
            ->action('Accept Invitation', $acceptUrl)
            ->line('[View the public band profile]('.route('bands.show', $this->band).')')
            ->line('Welcome to the music community!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'duration' => 'persistent',
            'title' => 'Band Invitation',
            'body' => "You've been invited to join {$this->band->name}".($this->position ? " as {$this->position}" : ''),
            'icon' => 'tabler-microphone-2',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'View Invitation',
                    'url' => "/band/{$this->band->slug}/accept-invitation",
                    'color' => 'primary',
                ],
            ],
            'invitation_id' => $this->invitation->id,
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
            'invitation_id' => $this->invitation->id,
            'band_id' => $this->band->id,
            'band_name' => $this->band->name,
            'role' => $this->role,
            'position' => $this->position,
        ];
    }
}
