<?php

namespace App\Notifications;

use App\Models\BandProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BandInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BandProfile $band,
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
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Band Invitation',
            'body' => "You've been invited to join {$this->band->name}" . ($this->position ? " as {$this->position}" : ''),
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
