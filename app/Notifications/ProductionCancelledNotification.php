<?php

namespace App\Notifications;

use App\Models\Production;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Production $production
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Event Cancelled: {$this->production->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("We regret to inform you that the following event has been cancelled:")
            ->line("**Event:** {$this->production->title}")
            ->line("**Originally Scheduled:** {$this->production->date_range}")
            ->line("**Venue:** {$this->production->venue_details}")
            ->line('We apologize for any inconvenience this may cause.')
            ->line('If you purchased tickets, refunds will be processed automatically.')
            ->action('View Events', route('filament.member.resources.productions.index'))
            ->salutation('The CMC Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Event Cancelled',
            'message' => "The event '{$this->production->title}' scheduled for {$this->production->date_range} has been cancelled.",
            'action_url' => route('filament.member.resources.productions.index'),
            'action_text' => 'View Events',
            'production_id' => $this->production->id,
        ];
    }
}