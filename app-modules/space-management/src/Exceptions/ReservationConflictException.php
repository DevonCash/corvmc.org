<?php

namespace CorvMC\SpaceManagement\Exceptions;

class ReservationConflictException extends \Exception
{
    protected $conflicts;

    public function __construct($message, $conflicts = [])
    {
        parent::__construct($message);
        $this->conflicts = $conflicts;
    }

    public function getConflicts()
    {
        return $this->conflicts;
    }
}
