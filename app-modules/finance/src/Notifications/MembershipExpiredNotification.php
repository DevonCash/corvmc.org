<?php

namespace CorvMC\Finance\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipExpiredNotification extends Notification implements ShouldQueue
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
            ->subject('Your CMC Membership Has Expired')
            ->greeting("Hello {$notifiable->name}!")
            ->line('We wanted to let you know that your Corvallis Music Collective membership has expired.')
            ->line('To continue enjoying member benefits like free practice hours and priority booking, please renew your membership.')
            ->action('Renew Membership', url('/member'))
            ->line('If you have any questions, please don\'t hesitate to contact us.')
            ->salutation('Thanks for being part of the CMC community!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Membership Expired',
            'message' => 'Your CMC membership has expired. Renew to continue enjoying member benefits.',
            'action_url' => url('/member'),
            'action_text' => 'Renew Membership',
        ];
    }
}
