<?php

namespace CorvMC\Membership\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserDeactivatedNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Deactivated - Corvallis Music Collective')
            ->greeting("Hello, {$notifiable->name}")
            ->line('Your account with Corvallis Music Collective has been deactivated.')
            ->line('Any future reservations have been automatically cancelled.')
            ->line('If you believe this was done in error, please contact us.')
            ->line('Thank you for being part of our community.');
    }
}
