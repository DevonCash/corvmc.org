<?php

namespace CorvMC\Events\States\TicketState;

use CorvMC\Events\States\TicketState;

class Cancelled extends TicketState
{
    public static $name = 'cancelled';

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'tabler-x';
    }

    public function getLabel(): string
    {
        return 'Cancelled';
    }

    public function getDescription(): string
    {
        return 'Ticket voided';
    }
}
