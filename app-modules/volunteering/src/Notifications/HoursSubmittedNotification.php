<?php

namespace CorvMC\Volunteering\Notifications;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HoursSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HourLog $hourLog,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $volunteer = $this->hourLog->user;
        $position = $this->hourLog->resolvePosition();
        $positionTitle = $position?->title ?? 'Unknown';

        return (new MailMessage)
            ->subject("Hours submitted for review: {$volunteer->name}")
            ->greeting('Hours submitted for review')
            ->line("**{$volunteer->name}** has submitted volunteer hours for review.")
            ->line("**Position:** {$positionTitle}")
            ->line("**Date:** {$this->hourLog->started_at->format('M j, Y')}")
            ->line("**Duration:** {$this->hourLog->formattedDuration()}")
            ->action('Review Pending Hours', url('/staff/volunteer/pending-hours'));
    }

}
