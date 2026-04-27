<?php

namespace CorvMC\Volunteering\Notifications;

use CorvMC\Volunteering\Models\HourLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShiftConfirmedNotification extends Notification implements ShouldQueue
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
            ->subject("You're confirmed: {$position->title}")
            ->greeting("You're confirmed!")
            ->line("You've been confirmed as **{$position->title}** for the following shift:");

        if ($event) {
            $mail->line("**Event:** {$event->title}");
        }

        $mail->line("**Date:** {$shift->start_at->format('l, M j, Y')}")
            ->line("**Time:** {$shift->start_at->format('g:i A')}–{$shift->end_at->format('g:i A')}")
            ->line('You can check in from the Volunteering page up to 30 minutes before your shift starts.');

        return $mail;
    }
}
