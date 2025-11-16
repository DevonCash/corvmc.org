<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportResolvedNotification extends Notification implements ShouldQueue
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
        $statusColor = $this->report->status === 'upheld' ? '🔴' : '✅';

        return (new MailMessage)
            ->subject("Your Report Has Been {$this->report->status_label}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$statusColor} Your report has been reviewed and **{$this->report->status_label}**.")
            ->line("**Reported Content:** {$contentType} - {$contentTitle}")
            ->line("**Your Report Reason:** {$this->report->reason_label}")
            ->when($this->report->resolution_notes, function ($message) {
                return $message->line("**Moderator Notes:** {$this->report->resolution_notes}");
            })
            ->when($this->report->status === 'upheld', function ($message) {
                return $message
                    ->line('Thank you for helping keep our community safe.')
                    ->line('Appropriate action has been taken regarding the reported content.');
            })
            ->when($this->report->status === 'dismissed', function ($message) {
                return $message
                    ->line('After review, this content was found to comply with our community guidelines.')
                    ->line('We appreciate your vigilance in reporting potential issues.');
            })
            ->line('Thank you for being part of our community moderation efforts.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'reportable_type' => $this->report->reportable_type,
            'reportable_id' => $this->report->reportable_id,
            'content_type' => $this->report->reportable->getReportableType(),
            'content_title' => $this->getContentTitle(),
            'status' => $this->report->status,
            'status_label' => $this->report->status_label,
            'resolution_notes' => $this->report->resolution_notes,
            'resolved_by' => $this->report->resolvedBy->name,
            'resolved_at' => $this->report->resolved_at,
        ];
    }

    private function getContentTitle(): string
    {
        $reportable = $this->report->reportable;

        return match (get_class($reportable)) {
            'App\Models\Event' => $reportable->title ?? 'Untitled Production',
            'App\Models\MemberProfile' => $reportable->user->name,
            'App\Models\Band' => $reportable->name ?? 'Untitled Band',
            default => "#{$reportable->id}",
        };
    }
}
