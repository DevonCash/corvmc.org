<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $lastReservation = $notifiable->reservations()->latest()->first();
        $lastReservationDate = $lastReservation 
            ? $lastReservation->created_at->format('F j, Y')
            : 'quite some time';

        return (new MailMessage)
            ->subject('We Miss You at the Corvallis Music Collective!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("We noticed it's been a while since your last visit to the practice space.")
            ->line("Your last reservation was on {$lastReservationDate}.")
            ->line('The music community isn\'t the same without you! Here\'s what you might have missed:')
            ->line('• New equipment and improvements to our practice spaces')
            ->line('• Upcoming shows and community events')
            ->line('• Fellow musicians looking for collaborators')
            ->action('Book a Practice Session', url('/member/reservations/create'))
            ->line('**Consider becoming a sustaining member** for just $10/month and get:')
            ->line('• 4 free practice hours per month')
            ->line('• Ability to book recurring sessions')
            ->line('• Support for the local music community')
            ->action('Learn About Sustaining Membership', url('/member'))
            ->line('Questions? Reply to this email or reach out to us anytime.')
            ->salutation('Hope to see you soon at CMC!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $lastReservation = $notifiable->reservations()->latest()->first();
        
        return [
            'user_id' => $this->user->id,
            'last_reservation_date' => $lastReservation?->created_at,
            'message' => 'Membership reminder sent to inactive user',
        ];
    }
}