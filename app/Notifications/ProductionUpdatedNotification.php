<?php

namespace App\Notifications;

use App\Models\Production;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Production $production,
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
        $subject = match ($this->updateType) {
            'published' => "Event Published: {$this->production->title}",
            'updated' => "Event Updated: {$this->production->title}",
            'cancelled' => "Event Cancelled: {$this->production->title}",
            'completed' => "Event Completed: {$this->production->title}",
            default => "Event Update: {$this->production->title}",
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello!')
            ->line($this->getUpdateMessage());

        if ($this->updateType === 'published') {
            $message->action('View Event Details', route('events.show', $this->production));
        }

        if (!empty($this->changes)) {
            $message->line('Changes made:');
            foreach ($this->changes as $field => $change) {
                $message->line("• {$field}: {$change['old']} → {$change['new']}");
            }
        }

        return $message->line('Thank you for being part of the Corvallis Music Collective!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->getNotificationTitle(),
            'body' => $this->getUpdateMessage(),
            'icon' => $this->getIcon(),
            'production_id' => $this->production->id,
            'production_title' => $this->production->title,
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
            'production_id' => $this->production->id,
            'production_title' => $this->production->title,
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
            'published' => "The event '{$this->production->title}' has been published and is now live!",
            'updated' => "The event '{$this->production->title}' has been updated with new information.",
            'cancelled' => "Unfortunately, the event '{$this->production->title}' has been cancelled.",
            'completed' => "The event '{$this->production->title}' has been completed. Thanks for attending!",
            default => "The event '{$this->production->title}' has been updated.",
        };
    }

    /**
     * Get the appropriate icon for the notification.
     */
    private function getIcon(): string
    {
        return match ($this->updateType) {
            'published' => 'heroicon-o-calendar',
            'updated' => 'heroicon-o-pencil-square',
            'cancelled' => 'heroicon-o-x-circle',
            'completed' => 'heroicon-o-check-circle',
            default => 'heroicon-o-bell',
        };
    }
}