<?php

namespace CorvMC\Events\Notifications;

use Carbon\Carbon;
use CorvMC\Events\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventRescheduledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public Carbon $oldStartDatetime
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Event Rescheduled: {$this->event->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("The event '{$this->event->title}' has been rescheduled.")
            ->line("**Previously:** {$this->oldStartDatetime->format('M j, Y g:i A')}")
            ->line("**New Date:** {$this->event->start_datetime->format('M j, Y g:i A')}")
            ->action('View Event', url("/member/events/{$this->event->id}"))
            ->line('Thank you for being part of the Corvallis Music Collective!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Event Rescheduled',
            'message' => "'{$this->event->title}' has been rescheduled from {$this->oldStartDatetime->format('M j, Y g:i A')} to {$this->event->start_datetime->format('M j, Y g:i A')}.",
            'event_id' => $this->event->id,
        ];
    }
}
