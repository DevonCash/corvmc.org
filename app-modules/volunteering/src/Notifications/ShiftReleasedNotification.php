<?php

namespace CorvMC\Volunteering\Notifications;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftReleasedNotification extends Notification implements ShouldQueue
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
        $shift = $this->hourLog->shift;
        $position = $shift->position;
        $event = $shift->event;

        $mail = (new MailMessage)
            ->subject("Shift update: {$position->title}")
            ->greeting('Shift update')
            ->line("You've been released from your **{$position->title}** shift.");

        if ($event) {
            $mail->line("**Event:** {$event->title}");
        }

        $mail->line("**Date:** {$shift->start_at->format('l, M j, Y')}")
            ->line("**Time:** {$shift->start_at->format('g:i A')}–{$shift->end_at->format('g:i A')}")
            ->line('If you believe this was a mistake, please reach out to a coordinator.');

        return $mail;
    }
}
