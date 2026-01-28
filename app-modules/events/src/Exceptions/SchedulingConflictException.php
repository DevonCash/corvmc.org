<?php

namespace CorvMC\Events\Exceptions;

use Exception;

/**
 * Exception thrown when an event cannot be scheduled due to conflicts.
 *
 * Listeners to the EventScheduling hook can throw this exception to
 * prevent event creation. The message should describe the conflict.
 */
class SchedulingConflictException extends Exception
{
    public function __construct(
        string $message = 'Event conflicts with existing schedule',
        public readonly array $conflicts = []
    ) {
        parent::__construct($message);
    }
}
