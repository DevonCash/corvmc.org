<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRefundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TicketOrder $order,
        public ?string $reason = null
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
            ->subject('Ticket Refund Processed - '.$event->title)
            ->greeting('Hello!')
            ->line('Your ticket order has been refunded.')
            ->line('')
            ->line('**Event:**')
            ->line($event->title)
            ->line($event->start_datetime->format('l, F j, Y \a\t g:i A'))
            ->line('')
            ->line('**Refund Details:**')
            ->line('Quantity: '.$this->order->quantity.' ticket(s)')
            ->line('Refund Amount: $'.number_format($this->order->total->getAmount()->toFloat(), 2));

        if ($this->reason) {
            $message->line('')
                ->line('**Reason:** '.$this->reason);
        }

        return $message
            ->line('')
            ->line('The refund will be credited to your original payment method within 5-10 business days.')
            ->line('If you have any questions, please contact us.');
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
            'title' => 'Ticket Refund Processed',
            'body' => "Your {$this->order->quantity} ticket(s) for {$event->title} have been refunded.",
            'icon' => 'tabler-receipt-refund',
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'reason' => $this->reason,
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
            'quantity' => $this->order->quantity,
            'refund_amount' => $this->order->total->getMinorAmount()->toInt(),
            'reason' => $this->reason,
        ];
    }
}
