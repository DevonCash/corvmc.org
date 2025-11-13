<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Event $event
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
            ->subject("Event Published: {$this->event->title}")
            ->greeting("Hello, {$notifiable->name}!")
            ->line("The event '{$this->event->title}' has been published and is now live!")
            ->line("Date: {$this->event->start_time->format('M j, Y g:i A')}")
            ->when($this->event->location, function ($mail) {
                $location = $this->event->location;
                if ($location->getVenueName()) {
                    $mail->line("Venue: {$location->getVenueName()}");
                }
            })
            ->action('View Event Details', url("/events/{$this->event->id}"))
            ->line('Thank you for being part of the Corvallis Music Collective community!');
    }
}
