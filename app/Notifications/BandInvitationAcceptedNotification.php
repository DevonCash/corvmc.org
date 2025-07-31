<?php

namespace App\Notifications;

use App\Models\BandProfile;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BandInvitationAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public BandProfile $band,
        public User $newMember
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
            'title' => 'New Band Member',
            'body' => "{$this->newMember->name} has joined {$this->band->name}",
            'icon' => 'heroicon-o-user-plus',
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
