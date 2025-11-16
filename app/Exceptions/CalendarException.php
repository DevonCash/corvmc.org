<?php

namespace App\Exceptions;

use Exception;

class CalendarException extends Exception
{
    public static function invalidDateRange(\DateTimeInterface $start, \DateTimeInterface $end): self
    {
        return new self("Invalid date range: start date ({$start->format('Y-m-d H:i:s')}) must be before end date ({$end->format('Y-m-d H:i:s')})");
    }

    public static function unsupportedModel(string $modelClass): self
    {
        return new self("Unsupported model class for calendar events: {$modelClass}. Supported models: Reservation, Event");
    }

    public static function missingRequiredData(string $field, string $context = ''): self
    {
        $message = "Missing required data: {$field}";
        if ($context) {
            $message .= " in context: {$context}";
        }

        return new self($message);
    }

    public static function conflictDetectionFailed(string $reason): self
    {
        return new self("Conflict detection failed: {$reason}");
    }

    public static function invalidEventData(string $reason): self
    {
        return new self("Invalid calendar event data: {$reason}");
    }

    public static function eventGenerationFailed(string $modelType, int $modelId, string $reason): self
    {
        return new self("Failed to generate calendar event for {$modelType} ID {$modelId}: {$reason}");
    }
}
