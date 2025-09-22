<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DonationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Transaction $transaction
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isRecurring = $this->transaction->type === 'recurring';
        $subject = $isRecurring
            ? 'Thank you for your ongoing support!'
            : 'Thank you for your donation!';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello!')
            ->line('Thank you so much for your generous ' . ($isRecurring ? 'monthly contribution' : 'donation') . ' to the Corvallis Music Collective!')
            ->line('**Donation Details:**')
            ->line('Amount: ' . $this->transaction->amount->formatTo('en_US'))
            ->line('Date: ' . $this->transaction->created_at->format('M j, Y g:i A'));

        if ($isRecurring) {
            $message->line('Type: Monthly Recurring Donation');
        }

        if ($this->transaction->transaction_id) {
            $message->line('Transaction ID: ' . $this->transaction->transaction_id);
        }

        $message->line('Your support helps us:')
            ->line('• Maintain our practice space and equipment')
            ->line('• Host community events and workshops')
            ->line('• Support local musicians and artists')
            ->line('• Keep our programs accessible to everyone');

        if ($isRecurring && $this->transaction->user && $this->transaction->amount->isGreaterThanOrEqualTo(10)) {
            $message->line('**Sustaining Member Benefits:**')
                ->line('As a sustaining member, you now have access to:')
                ->line('• 4 free practice space hours each month')
                ->line('• Priority booking for events')
                ->line('• Exclusive member updates');
        }

        return $message
            ->line('You are helping build a vibrant music community in Corvallis!')
            ->line('With gratitude,')
            ->line('The Corvallis Music Collective Team');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $isRecurring = $this->transaction->type === 'recurring';

        return [
            'title' => $isRecurring ? 'Monthly Donation Received' : 'Donation Received',
            'body' => 'Thank you for your ' . $this->transaction->amount->formatTo('en_US') . ' ' . ($isRecurring ? 'monthly donation' : 'donation') . '!',
            'icon' => 'heroicon-o-heart',
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount,
            'type' => $this->transaction->type,
            'is_recurring' => $isRecurring,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount,
            'type' => $this->transaction->type,
            'is_recurring' => $this->transaction->type === 'recurring',
        ];
    }
}
