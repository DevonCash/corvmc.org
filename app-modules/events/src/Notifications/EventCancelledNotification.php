<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Event Cancelled: {$this->event->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line('We regret to inform you that the following event has been cancelled:')
            ->line("**Event:** {$this->event->title}")
            ->line("**Originally Scheduled:** {$this->event->date_range}")
            ->line("**Venue:** {$this->event->venue_details}")
            ->line('We apologize for any inconvenience this may cause.')
            ->line('If you purchased tickets, refunds will be processed automatically.')
            ->action('View Events', route('filament.member.resources.events.index'))
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Event Cancelled',
            'message' => "The event '{$this->event->title}' scheduled for {$this->event->date_range} has been cancelled.",
            'action_url' => route('filament.member.resources.events.index'),
            'action_text' => 'View Events',
            'event_id' => $this->event->id,
        ];
    }
}
