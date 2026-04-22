<?php

namespace CorvMC\Events\States\TicketState;

use CorvMC\Events\States\TicketState;

class Valid extends TicketState
{
    public static $name = 'valid';

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'tabler-ticket';
    }

    public function getLabel(): string
    {
        return 'Valid';
    }

    public function getDescription(): string
    {
        return 'Ready for check-in';
    }
}
