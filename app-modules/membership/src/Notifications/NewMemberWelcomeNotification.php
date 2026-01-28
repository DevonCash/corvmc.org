<?php

namespace CorvMC\Membership\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMemberWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to the Corvallis Music Collective!')
            ->greeting("Welcome {$notifiable->name}!")
            ->line('Thank you for joining the Corvallis Music Collective community!')
            ->line('Here are some things you can do now that you\'re a member:')
            ->line('• Book practice space time')
            ->line('• Create and manage your member profile')
            ->line('• Join or create band profiles')
            ->line('• Stay updated on CMC events and shows')
            ->action('Complete Your Profile', url('/member'))
            ->line('If you have any questions, feel free to reach out to us.')
            ->line('We\'re excited to have you as part of our music community!')
            ->salutation('Welcome aboard,<br>The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to CMC!',
            'message' => 'Welcome to the Corvallis Music Collective! Complete your profile to get started.',
            'action_url' => url('/member'),
            'action_text' => 'Complete Profile',
        ];
    }
}
