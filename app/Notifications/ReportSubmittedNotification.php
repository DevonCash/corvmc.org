<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * @property Report $report
 */
class ReportSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Report $report
    ) {}

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
        $contentType = $this->report->reportable->getReportableType();
        $contentTitle = $this->getContentTitle();

        return (new MailMessage)
            ->subject("Content Report Submitted: {$contentType}")
            ->greeting("Hello {$notifiable->name},")
            ->line('A new report has been submitted that requires moderation attention.')
            ->line("**Reported Content:** {$contentType} - {$contentTitle}")
            ->line("**Reason:** {$this->report->reason_label}")
            ->line("**Reported By:** {$this->report->reportedBy->name}")
            ->when($this->report->custom_reason, function ($message) {
                return $message->line("**Additional Details:** {$this->report->custom_reason}");
            })
            ->action('Review Report', route('filament.member.resources.reports.reports.view', $this->report))
            ->line('Please review this report and take appropriate action.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var \App\Contracts\Reportable $reportable */
        $reportable = $this->report->reportable;

        return [
            'report_id' => $this->report->id,
            'reportable_type' => $this->report->reportable_type,
            'reportable_id' => $this->report->reportable_id,
            'content_type' => $reportable->getReportableType(),
            'content_title' => $this->getContentTitle(),
            'reason' => $this->report->reason_label,
            'reporter_name' => $this->report->reportedBy->name,
            'custom_reason' => $this->report->custom_reason,
            'submitted_at' => $this->report->created_at,
        ];
    }

    private function getContentTitle(): string
    {
        $reportable = $this->report->reportable;

        if ($reportable instanceof \App\Models\Event) {
            return $reportable->title ?? 'Untitled Production';
        }

        if ($reportable instanceof \App\Models\MemberProfile) {
            /** @var \App\Models\User $user */
            $user = $reportable->user;
            return $user->name;
        }

        if ($reportable instanceof \App\Models\Band) {
            return $reportable->name ?? 'Untitled Band';
        }

        return "#{$reportable->getKey()}";
    }
}
