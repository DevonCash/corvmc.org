<?php

namespace App\Notifications;

use App\Models\Band;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BandClaimedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Band $band,
        public User $claimer
    ) {
        //
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Band \"{$this->band->name}\" Has Been Claimed")
            ->greeting("Hello!")
            ->line("The band \"{$this->band->name}\" has been claimed by {$this->claimer->name}.")
            ->line("This guest band profile now has an active owner who can manage the band's information and invite members.")
            ->line('Band Details:')
            ->line("• Name: {$this->band->name}")
            ->line("• Owner: {$this->claimer->name}")
            ->when($this->band->hometown, fn($mail) => $mail->line("• Location: {$this->band->hometown}"))
            ->action('View Band Profile', route('filament.member.resources.bands.view', $this->band))
            ->line('Thank you for using our platform!');
    }

    public function toDatabase($notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Band Claimed',
            'message' => "{$this->claimer->name} claimed ownership of \"{$this->band->name}\"",
            'band_id' => $this->band->id,
            'claimer_id' => $this->claimer->id,
            'action_url' => route('filament.member.resources.bands.view', $this->band),
        ]);
    }
}