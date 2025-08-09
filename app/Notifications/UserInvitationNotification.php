<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $invitedUser,
        public string $invitationToken,
        public array $roles = []
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
    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = URL::signedRoute('invitation.accept', ['token' => $this->invitationToken]);
        
        $rolesText = empty($this->roles) 
            ? 'as a member' 
            : 'with the role(s): ' . implode(', ', $this->roles);

        return (new MailMessage)
            ->subject('Welcome to Corvallis Music Collective!')
            ->greeting('Hello!')
            ->line('You have been invited to join the Corvallis Music Collective ' . $rolesText . '.')
            ->line('The Corvallis Music Collective is a community-driven space for musicians to connect, collaborate, and create.')
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line('If you have any questions, feel free to contact us.')
            ->line('Welcome to the community!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Invitation to Join CMC',
            'body' => 'You have been invited to join the Corvallis Music Collective.',
            'icon' => 'heroicon-o-paper-airplane',
            'user_id' => $this->invitedUser->id,
            'roles' => $this->roles,
            'token' => $this->invitationToken,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->invitedUser->id,
            'roles' => $this->roles,
            'token' => $this->invitationToken,
        ];
    }
}