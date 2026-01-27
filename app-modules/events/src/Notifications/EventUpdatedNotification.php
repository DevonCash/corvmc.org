<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EventUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public string $updateType,
        public array $changes = []
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        try {
            $subject = match ($this->updateType) {
                'published' => "Event Published: {$this->event->title}",
                'updated' => "Event Updated: {$this->event->title}",
                'cancelled' => "Event Cancelled: {$this->event->title}",
                'completed' => "Event Completed: {$this->event->title}",
                default => "Event Update: {$this->event->title}",
            };

            $message = (new MailMessage)
                ->subject($subject)
                ->greeting('Hello!')
                ->line($this->getUpdateMessage());

            if ($this->updateType === 'published') {
                $message->action('View Event Details', route('events.show', $this->event));
            }

            if (! empty($this->changes)) {
                $message->line('Changes made:');
                foreach ($this->changes as $field => $change) {
                    // Handle both array format and simple string format
                    if (is_array($change) && isset($change['old'], $change['new'])) {
                        $message->line("• {$field}: {$change['old']} → {$change['new']}");
                    } else {
                        // If change is a simple string or doesn't have old/new keys
                        $changeText = is_string($change) ? $change : json_encode($change);
                        $message->line("• {$field}: {$changeText}");
                    }
                }
            }

            return $message->line('Thank you for being part of the Corvallis Music Collective!');
        } catch (\Exception $e) {
            Log::error('Failed to build EventUpdatedNotification email', [
                'event_id' => $this->event->id,
                'update_type' => $this->updateType,
                'error' => $e->getMessage(),
            ]);

            // Return a simple fallback message
            return (new MailMessage)
                ->subject("Event Update: {$this->event->title}")
                ->greeting('Hello!')
                ->line("The event '{$this->event->title}' has been updated.")
                ->line('Thank you for being part of the Corvallis Music Collective!');
        }
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'format' => 'filament',
            'title' => $this->getNotificationTitle(),
            'body' => $this->getUpdateMessage(),
            'icon' => $this->getIcon(),
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'update_type' => $this->updateType,
            'changes' => $this->changes,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'update_type' => $this->updateType,
            'changes' => $this->changes,
        ];
    }

    /**
     * Get the notification title based on update type.
     */
    private function getNotificationTitle(): string
    {
        return match ($this->updateType) {
            'published' => 'Event Published',
            'updated' => 'Event Updated',
            'cancelled' => 'Event Cancelled',
            'completed' => 'Event Completed',
            default => 'Event Update',
        };
    }

    /**
     * Get the update message based on type.
     */
    private function getUpdateMessage(): string
    {
        return match ($this->updateType) {
            'published' => "The event '{$this->event->title}' has been published and is now live!",
            'updated' => "The event '{$this->event->title}' has been updated with new information.",
            'cancelled' => "Unfortunately, the event '{$this->event->title}' has been cancelled.",
            'completed' => "The event '{$this->event->title}' has been completed. Thanks for attending!",
            default => "The event '{$this->event->title}' has been updated.",
        };
    }

    /**
     * Get the appropriate icon for the notification.
     */
    private function getIcon(): string
    {
        return match ($this->updateType) {
            'published' => 'tabler-clock-check',
            'updated' => 'tabler-circle-arrow-up',
            'cancelled' => 'tabler-circle-x',
            'completed' => 'tabler-circle-check',
            default => 'tabler-bell',
        };
    }
}
