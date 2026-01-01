<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordResetNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-PM-Track-Opens' => 'false',
            'X-PM-Track-Links' => 'false',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        // Use the signed URL if set by Filament, otherwise fall back to unsigned URL
        $resetUrl = $this->url ?? url(route('filament.member.auth.password-reset.reset', ['token' => $this->token, 'email' => $notifiable->email]));
        
        return (new MailMessage)
            ->subject('Reset Your CMC Password')
            ->greeting("Hello {$notifiable->name}!")
            ->line('You are receiving this email because we received a password reset request for your CMC account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will expire in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('The CMC Team');
    }
}
