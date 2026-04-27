<?php

namespace CorvMC\Volunteering\Notifications;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HoursReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HourLog $hourLog,
        public bool $approved,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $position = $this->hourLog->resolvePosition();
        $positionTitle = $position?->title ?? 'Unknown';

        if ($this->approved) {
            return (new MailMessage)
                ->subject("Hours approved: {$positionTitle}")
                ->greeting('Hours approved!')
                ->line("Your submitted hours for **{$positionTitle}** have been approved.")
                ->line("**Hours:** {$this->formatMinutes()}")
                ->line('Thanks for volunteering!');
        }

        $mail = (new MailMessage)
            ->subject("Hours update: {$positionTitle}")
            ->greeting('Hours update')
            ->line("Your submitted hours for **{$positionTitle}** were not approved.");

        if ($this->hourLog->notes) {
            $mail->line("**Reviewer notes:** {$this->hourLog->notes}");
        }

        $mail->line('If you have questions, please reach out to a coordinator.');

        return $mail;
    }

    private function formatMinutes(): string
    {
        $minutes = $this->hourLog->minutes;

        if ($minutes === null) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return "{$remainder}m";
        }

        if ($remainder === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remainder}m";
    }
}
