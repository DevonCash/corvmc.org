<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Headers;

class PasswordResetNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public string $url;

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-PM-Track-Opens' => 'false',
            'X-PM-Track-Links' => 'false',
        ]);
    }

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
