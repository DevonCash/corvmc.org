<?php

namespace CorvMC\Moderation\Notifications;

use CorvMC\Moderation\Models\Revision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Revision $revision;

    /**
     * Create a new notification instance.
     */
    public function __construct(Revision $revision)
    {
        $this->revision = $revision;
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
        $changesSummary = $this->revision->getChangesSummary();

        $mailMessage = (new MailMessage)
            ->subject("Your {$modelName} Revision Has Been Approved")
            ->greeting("Hello {$notifiable->name}!")
            ->line('Great news! Your revision has been approved and applied.')
            ->line("**Model:** {$modelName}")
            ->line("**Changes:** {$changesSummary}");

        if ($this->revision->review_reason) {
            $mailMessage->line("**Reviewer Notes:** {$this->revision->review_reason}");
        }

        if ($this->revision->auto_approved) {
            $mailMessage->line('✅ This revision was automatically approved based on your trust level.');
        }

        $mailMessage->action('View Updated Content', $this->getModelUrl())
            ->line('Thank you for contributing to our community!');

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
            'changes_summary' => $this->revision->getChangesSummary(),
            'review_reason' => $this->revision->review_reason,
            'auto_approved' => $this->revision->auto_approved,
            'approved_at' => $this->revision->reviewed_at,
        ];
    }

    /**
     * Get URL to view the updated model.
     */
    protected function getModelUrl(): string
    {
        $modelType = $this->revision->revisionable_type;
        $modelId = $this->revision->revisionable_id;

        return match ($modelType) {
            'App\Models\MemberProfile' => url("/member/member-profiles/{$modelId}"),
            'App\Models\Band' => url("/member/bands/{$modelId}"),
            'CorvMC\Events\Models\Event' => route('events.show', $modelId),
            default => url('/member')
        };
    }
}
