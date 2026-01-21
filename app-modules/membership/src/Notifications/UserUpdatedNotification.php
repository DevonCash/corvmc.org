<?php

namespace CorvMC\Membership\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private array $originalData,
        private array $newData
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
        $changes = $this->getChanges();

        return (new MailMessage)
            ->subject('Your Account Has Been Updated')
            ->greeting("Hello, {$notifiable->name}!")
            ->line('Your account information has been updated.')
            ->when(! empty($changes), function ($mail) use ($changes) {
                $mail->line('Changes made:');
                foreach ($changes as $change) {
                    $mail->line("• {$change}");
                }
            })
            ->action('View Account', url('/member'))
            ->line('If you did not request these changes, please contact us immediately.');
    }

    /**
     * Get the list of changes for display.
     */
    private function getChanges(): array
    {
        $changes = [];

        if (isset($this->newData['email']) && $this->originalData['email'] !== $this->newData['email']) {
            $changes[] = 'Email address updated';
        }

        if (isset($this->newData['name']) && $this->originalData['name'] !== $this->newData['name']) {
            $changes[] = 'Name updated';
        }

        return $changes;
    }
}
