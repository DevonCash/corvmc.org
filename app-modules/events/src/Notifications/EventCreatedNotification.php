<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCreatedNotification extends Notification
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
            ->subject('New Event Created')
            ->greeting("Hello, {$notifiable->name}!")
            ->line("A new event '{$this->event->title}' has been created.")
            ->line("Date: {$this->event->start_time->format('M j, Y g:i A')}")
            ->action('View Event', url("/member/events/{$this->event->id}"))
            ->line('You can manage this event from your dashboard.');
    }
}
