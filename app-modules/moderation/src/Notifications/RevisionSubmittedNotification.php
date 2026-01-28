<?php

namespace CorvMC\Moderation\Notifications;

use CorvMC\Moderation\Models\Revision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Revision $revision;

    protected string $priority;

    /**
     * Create a new notification instance.
     */
    public function __construct(Revision $revision, string $priority = 'standard')
    {
        $this->revision = $revision;
        $this->priority = $priority;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        $modelName = $this->revision->getModelTypeName();
        $submitterName = $this->revision->submittedBy?->name;
        $changesSummary = $this->revision->getChangesSummary();

        $mailMessage = (new MailMessage)
            ->subject("New {$modelName} Revision Awaiting Review")
            ->greeting("Hello {$notifiable->name}!")
            ->line('A new revision has been submitted for review.')
            ->line("**Model:** {$modelName}")
            ->line("**Submitted by:** {$submitterName}")
            ->line("**Changes:** {$changesSummary}")
            ->line('**Priority:** '.ucfirst($this->priority))
            ->action('Review Revision', url("/member/revisions/{$this->revision->id}"))
            ->line('Please review this revision at your earliest convenience.');

        if ($this->priority === 'urgent') {
            $mailMessage->line('⚠️ **This revision requires urgent attention.**');
        }

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'revision_id' => $this->revision->id,
            'model_type' => $this->revision->getModelTypeName(),
            'model_id' => $this->revision->revisionable_id,
            'submitter_name' => $this->revision->submittedBy->name,
            'submitter_id' => $this->revision->submitted_by_id,
            'changes_summary' => $this->revision->getChangesSummary(),
            'priority' => $this->priority,
            'created_at' => $this->revision->created_at,
        ];
    }
}
