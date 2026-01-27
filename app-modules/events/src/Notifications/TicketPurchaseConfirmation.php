<?php

namespace CorvMC\Events\Notifications;

use CorvMC\Events\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketPurchaseConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TicketOrder $order
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
        $tickets = $this->order->tickets;

        $message = (new MailMessage)
            ->subject('Your Tickets for '.$event->title)
            ->greeting('Thank you for your purchase!')
            ->line('Your ticket order has been confirmed.')
            ->line('')
            ->line('**Event Details:**')
            ->line('Event: '.$event->title)
            ->line('Date: '.$event->start_datetime->format('l, F j, Y'))
            ->line('Time: '.$event->start_datetime->format('g:i A'))
            ->line('Venue: '.$event->venue_name)
            ->line('')
            ->line('**Order Summary:**')
            ->line('Quantity: '.$this->order->quantity.' ticket(s)')
            ->line('Total: $'.number_format($this->order->total->getAmount()->toFloat(), 2));

        // Add ticket codes
        if ($tickets->isNotEmpty()) {
            $message->line('')
                ->line('**Your Ticket Code(s):**');

            foreach ($tickets as $ticket) {
                $message->line('- '.$ticket->code);
            }

            $message->line('')
                ->line('Please present these codes at the door for entry.');
        }

        if ($event->doors_datetime) {
            $message->line('')
                ->line('Doors open at: '.$event->doors_datetime->format('g:i A'));
        }

        return $message
            ->action('View Your Tickets', route('filament.member.pages.my-tickets'))
            ->line('We look forward to seeing you at the event!');
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
            'title' => 'Tickets Purchased',
            'body' => "Your {$this->order->quantity} ticket(s) for {$event->title} on {$event->start_datetime->format('M j, Y')} have been confirmed.",
            'icon' => 'tabler-ticket',
            'order_id' => $this->order->id,
            'order_uuid' => $this->order->uuid,
            'event_id' => $event->id,
            'event_title' => $event->title,
            'quantity' => $this->order->quantity,
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
            'total' => $this->order->total->getMinorAmount()->toInt(),
        ];
    }
}
