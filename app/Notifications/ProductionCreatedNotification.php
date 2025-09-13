<?php

namespace App\Notifications;

use App\Models\Production;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Production $production
    ) {}

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
            ->subject('New Production Created')
            ->greeting("Hello, {$notifiable->name}!")
            ->line("A new production '{$this->production->title}' has been created.")
            ->line("Date: {$this->production->start_time->format('M j, Y g:i A')}")
            ->action('View Production', url("/member/productions/{$this->production->id}"))
            ->line('You can manage this production from your dashboard.');
    }
}