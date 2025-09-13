<?php

namespace App\Notifications;

use App\Models\Invitation;
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
        public Invitation $invitation,
        public array $data = []
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
        $acceptUrl = route('invitation.accept', ['token' => $this->invitation->token]);

        $rolesText = empty($this->data['roles'])
            ? 'as a member'
            : 'with the role(s): ' . implode(', ', $this->data['roles']);

        return (new MailMessage)
            ->subject('Welcome to Corvallis Music Collective!')
            ->greeting('Hello!')
            ->line('You have been invited to join the Corvallis Music Collective ' . $rolesText . '.')
            ->when(!empty($this->invitation->message), function ($message) {
                return $message->line('Message from the inviter: "' . $this->invitation->message . '"');
            })
            ->when(!empty($this->invitation->inviter), function ($message) {
                return $message->line('Invited by: ' . $this->invitation->inviter->name);
            })
            ->line('The Corvallis Music Collective is a community-driven space for musicians to connect, collaborate, and create.')
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line('If you have any questions, feel free to contact us.')
            ->line('Welcome to the community!');
    }
}
