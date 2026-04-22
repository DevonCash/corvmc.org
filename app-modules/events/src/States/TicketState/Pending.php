<?php

namespace CorvMC\Events\States\TicketState;

use CorvMC\Events\States\TicketState;

class Pending extends TicketState
{
    public static $name = 'pending';

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): string
    {
        return 'tabler-clock';
    }

    public function getLabel(): string
    {
        return 'Pending';
    }

    public function getDescription(): string
    {
        return 'Awaiting order settlement';
    }
}
