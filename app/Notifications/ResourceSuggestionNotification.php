<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResourceSuggestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $submissionData
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Local Resource Suggestion: '.$this->submissionData['resource_name'])
            ->greeting('New Resource Suggestion')
            ->line('Someone has suggested a new local resource for the directory.')
            ->line('---')
            ->line('**Resource Name:** '.$this->submissionData['resource_name'])
            ->line('**Category:** '.$this->submissionData['category_name']);

        if ($this->submissionData['website'] ?? null) {
            $mail->line('**Website:** '.$this->submissionData['website']);
        }

        if ($this->submissionData['description'] ?? null) {
            $mail->line('**Description:** '.$this->submissionData['description']);
        }

        if ($this->submissionData['contact_name'] ?? null) {
            $mail->line('**Contact Name:** '.$this->submissionData['contact_name']);
        }

        if ($this->submissionData['contact_phone'] ?? null) {
            $mail->line('**Contact Phone:** '.$this->submissionData['contact_phone']);
        }

        if ($this->submissionData['address'] ?? null) {
            $mail->line('**Address:** '.$this->submissionData['address']);
        }

        $mail->line('---')
            ->line('Review this suggestion and add it to the Local Resources page if appropriate.');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->submissionData;
    }
}
