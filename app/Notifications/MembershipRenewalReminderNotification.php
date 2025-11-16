<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipRenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
        public int $daysUntilExpiry
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->daysUntilExpiry === 1
            ? 'Your CMC Membership Expires Tomorrow'
            : "Your CMC Membership Expires in {$this->daysUntilExpiry} Days";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your Corvallis Music Collective membership expires in {$this->daysUntilExpiry} days.")
            ->line('Renew now to avoid any interruption to your member benefits including free practice hours and priority booking.')
            ->action('Renew Membership', url('/member'))
            ->line('Thank you for supporting the CMC!')
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Membership Renewal Reminder',
            'message' => "Your CMC membership expires in {$this->daysUntilExpiry} days.",
            'action_url' => url('/member'),
            'action_text' => 'Renew Membership',
            'days_until_expiry' => $this->daysUntilExpiry,
        ];
    }
}
