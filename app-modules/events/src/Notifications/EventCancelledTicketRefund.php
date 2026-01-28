<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelledTicketRefund extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TicketOrder $order,
        public ?string $cancellationReason = null
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
        $event = $this->order->event;

        $message = (new MailMessage)
            ->subject('Event Cancelled - '.$event->title.' - Refund Processed')
            ->greeting('Hello!')
            ->line('We regret to inform you that the following event has been cancelled:')
            ->line('')
            ->line('**'.$event->title.'**')
            ->line('Originally scheduled for: '.$event->start_datetime->format('l, F j, Y \a\t g:i A'))
            ->line('Venue: '.$event->venue_name);

        if ($this->cancellationReason) {
            $message->line('')
                ->line('**Reason for cancellation:** '.$this->cancellationReason);
        }

        return $message
            ->line('')
            ->line('**Your tickets have been automatically refunded:**')
            ->line('Quantity: '.$this->order->quantity.' ticket(s)')
            ->line('Refund Amount: $'.number_format($this->order->total->getAmount()->toFloat(), 2))
            ->line('')
            ->line('The refund will be credited to your original payment method within 5-10 business days.')
            ->line('')
            ->action('Browse Upcoming Events', route('events.index'))
            ->line('We apologize for any inconvenience and hope to see you at a future event.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $event = $this->order->event;

        return [
            'format' => 'filament',
            'duration' => 'persistent',
            'title' => 'Event Cancelled - Refund Issued',
            'body' => "{$event->title} has been cancelled. Your {$this->order->quantity} ticket(s) have been refunded.",
            'icon' => 'tabler-calendar-cancel',
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'cancellation_reason' => $this->cancellationReason,
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'event_id' => $this->order->event_id,
            'event_title' => $this->order->event->title ?? 'Unknown Event',
            'quantity' => $this->order->quantity,
            'refund_amount' => $this->order->total->getMinorAmount()->toInt(),
            'cancellation_reason' => $this->cancellationReason,
        ];
    }
}
