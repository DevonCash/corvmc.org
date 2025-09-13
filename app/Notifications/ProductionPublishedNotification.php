<?php

namespace App\Notifications;

use App\Models\Production;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionPublishedNotification extends Notification
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
            ->subject("Event Published: {$this->production->title}")
            ->greeting("Hello, {$notifiable->name}!")
            ->line("The production '{$this->production->title}' has been published and is now live!")
            ->line("Date: {$this->production->start_time->format('M j, Y g:i A')}")
            ->when($this->production->location, function ($mail) {
                $location = $this->production->location;
                if ($location->getVenueName()) {
                    $mail->line("Venue: {$location->getVenueName()}");
                }
            })
            ->action('View Event Details', url("/events/{$this->production->id}"))
            ->line('Thank you for being part of the Corvallis Music Collective community!');
    }
}
