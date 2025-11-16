<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class EmailVerificationNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your CMC Email Address')
            ->greeting("Hello {$notifiable->name}!")
            ->line('Please click the button below to verify your email address with the Corvallis Music Collective.')
            ->action('Verify Email Address', $this->verificationUrl($notifiable))
            ->line('If you did not create a CMC account, no further action is required.')
            ->salutation('The CMC Team');
    }
}
