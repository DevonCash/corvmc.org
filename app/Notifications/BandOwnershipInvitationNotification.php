<?php

namespace App\Notifications;

use App\Models\Band;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BandOwnershipInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private User $user,
        private Band $band,
        private string $token,
    ) {}

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
        $invitationUrl = route('invitation.complete', ['token' => $this->token]);

        return (new MailMessage)
            ->subject("You're invited to join Corvallis Music Collective and own {$this->band->name}!")
            ->greeting('Hi there!')
            ->line("You've been invited to join the Corvallis Music Collective and take ownership of the band profile for **{$this->band->name}**.")
            ->line("As the band owner, you'll be able to:")
            ->line("• Manage your band's profile and information")
            ->line('• Invite other members to join your band')
            ->line('• Book shows and manage performances')
            ->line('• Connect with other local musicians')
            ->action('Complete Your Registration', $invitationUrl)
            ->line("Once you complete your registration, you'll have full control over your band's profile.")
            ->line('Welcome to the Corvallis Music Collective community!')
            ->salutation('The CMC Team');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'band_ownership_invitation',
            'title' => 'Band Ownership Invitation',
            'body' => "You've been invited to join CMC and own {$this->band->name}",
            'band_id' => $this->band->id,
            'band_name' => $this->band->name,
            'token' => $this->token,
        ];
    }
}
