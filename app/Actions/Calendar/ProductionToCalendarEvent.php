<?php

namespace App\Actions\Calendar;

use App\Exceptions\CalendarException;
use App\Models\Production;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductionToCalendarEvent
{
    use AsAction;

    /**
     * Convert a production to a calendar event with proper styling.
     */
    public function handle(Production $production): CalendarEvent
    {
        if (!$production->exists) {
            throw CalendarException::missingRequiredData('production', 'Production must be persisted to database');
        }

        if (!$production->start_time || !$production->end_time) {
            throw CalendarException::missingRequiredData('production times', 'Production must have start and end times');
        }

        if ($production->start_time >= $production->end_time) {
            throw CalendarException::invalidDateRange($production->start_time, $production->end_time);
        }

        try {
            // Only show productions that use the practice space
            if (!$production->usesPracticeSpace()) {
                return CalendarEvent::make($production)
                    ->title('')
                    ->start($production->start_time->toISOString())
                    ->end($production->end_time->toISOString())
                    ->display('none');
            }

            $title = $this->getProductionTitle($production);
            $color = $this->getProductionColor($production->status);
            $extendedProps = $this->getProductionExtendedProps($production);

            return CalendarEvent::make($production)
                ->title($title)
                ->start($production->start_time)
                ->end($production->end_time)
                ->backgroundColor($color)
                ->textColor('#fff')
                ->extendedProps($extendedProps);
        } catch (\Exception $e) {
            if ($e instanceof CalendarException) {
                throw $e;
            }
            throw CalendarException::eventGenerationFailed(
                'Production',
                $production->id,
                $e->getMessage()
            );
        }
    }

    /**
     * Get production title with status indicators.
     */
    private function getProductionTitle(Production $production): string
    {
        $title = $production->title;

        if (!$production->isPublished()) {
            $title .= ' (Draft)';
        }

        return $title;
    }

    /**
     * Get color for production status.
     */
    private function getProductionColor(string $status): string
    {
        return match ($status) {
            'pre-production' => '#8b5cf6', // purple
            'production' => '#3b82f6',     // blue
            'completed' => '#10b981',      // green
            'cancelled' => '#ef4444',      // red
            default => '#6b7280',          // gray
        };
    }

    /**
     * Get extended properties for production calendar events.
     */
    private function getProductionExtendedProps(Production $production): array
    {
        return [
            'type' => 'production',
            'manager_name' => $production->manager->name ?? '',
            'status' => $production->status,
            'venue_name' => $production->venue_name,
            'is_published' => $production->isPublished(),
            'ticket_url' => $production->ticket_url,
        ];
    }
}
