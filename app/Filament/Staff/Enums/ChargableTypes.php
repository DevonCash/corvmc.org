<?php

use Filament\Support\Contracts\HasLabel;

enum ChargableTypes: string implements HasLabel
{
    case RehearsalReservation = 'rehearsal_reservation';
    case EventTicket = 'event_ticket';
    case Membership = 'membership';

    public function getLabel(): string
    {
        return match ($this) {
            self::RehearsalReservation => 'Rehearsal Reservation',
            self::EventTicket => 'Event Ticket',
            self::Membership => 'Membership',
        };
    }


    public function getRoute(): string
    {
        return match ($this) {
            self::RehearsalReservation => 'filament.staff.space-management.view',
            self::EventTicket => 'filament.staff.resources.events.ticket-orders.view',
            self::Membership => 'staff.memberships.view',
        };
    }
}
