<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactFormSubmissionNotification extends Notification implements ShouldQueue
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
        $subjectLabels = [
            'general' => 'General Inquiry',
            'membership' => 'Membership Questions',
            'practice_space' => 'Practice Space',
            'performance' => 'Performance Inquiry',
            'volunteer' => 'Volunteer Opportunities',
            'donation' => 'Donations & Support',
        ];

        $subjectLabel = $subjectLabels[$this->submissionData['subject']] ?? ucfirst($this->submissionData['subject']);

        return (new MailMessage)
            ->subject('Contact Form: '.$subjectLabel)
            ->greeting('New Contact Form Submission')
            ->line('**From:** '.$this->submissionData['name'])
            ->line('**Email:** '.$this->submissionData['email'])
            ->lineIf($this->submissionData['phone'] ?? null, '**Phone:** '.($this->submissionData['phone'] ?? ''))
            ->line('**Subject:** '.$subjectLabel)
            ->line('**Message:**')
            ->line($this->submissionData['message'])
            ->action('Reply to Contact', 'mailto:'.$this->submissionData['email'])
            ->line('This message was submitted through the contact form on the CMC website.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->submissionData;
    }
}
