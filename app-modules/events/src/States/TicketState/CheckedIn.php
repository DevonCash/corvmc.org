<?php

namespace CorvMC\Events\States\TicketState;

use CorvMC\Events\States\TicketState;

class CheckedIn extends TicketState
{
    public static $name = 'checked_in';

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): string
    {
        return 'tabler-circle-check';
    }

    public function getLabel(): string
    {
        return 'Checked In';
    }

    public function getDescription(): string
    {
        return 'Attendee has entered';
    }
}
