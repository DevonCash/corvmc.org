<?php

namespace CorvMC\Kiosk\Http\Controllers;

use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EventController extends Controller
{
    /**
     * List events that have ticketing enabled.
     */
    public function index(Request $request): JsonResponse
    {
        $events = Event::query()
            ->where('ticketing_enabled', true)
            ->where('start_datetime', '>=', now()->startOfDay())
            ->where('start_datetime', '<=', now()->addDays(7)->endOfDay())
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (Event $event) => $this->formatEvent($event));

        return response()->json(['events' => $events]);
    }

    /**
     * Get a single event.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        if (! $event->ticketing_enabled) {
            return response()->json([
                'message' => 'This event does not have ticketing enabled.',
            ], 404);
        }

        return response()->json(['event' => $this->formatEvent($event)]);
    }

    /**
     * Get event statistics (sold, capacity, checked in).
     */
    public function stats(Request $request, Event $event): JsonResponse
    {
        if (! $event->ticketing_enabled) {
            return response()->json([
                'message' => 'This event does not have ticketing enabled.',
            ], 404);
        }

        $checkedInCount = $event->tickets()
            ->where('tickets.status', TicketStatus::CheckedIn)
            ->count();

        return response()->json([
            'sold' => $event->tickets_sold ?? 0,
            'capacity' => $event->ticket_quantity,
            'checked_in' => $checkedInCount,
        ]);
    }

    /**
     * Format an event for the API response.
     */
    private function formatEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'start_datetime' => $event->start_datetime->toIso8601String(),
            'venue_name' => $event->venue_name,
            'ticket_quantity' => $event->ticket_quantity,
            'tickets_sold' => $event->tickets_sold ?? 0,
            'base_price' => $event->getBaseTicketPrice()->getMinorAmount()->toInt(),
        ];
    }
}
