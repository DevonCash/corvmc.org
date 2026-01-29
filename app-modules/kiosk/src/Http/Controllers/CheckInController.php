<?php

namespace CorvMC\Kiosk\Http\Controllers;

use CorvMC\Events\Enums\TicketStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckInController extends Controller
{
    /**
     * Check in a ticket by code.
     */
    public function checkIn(Request $request, Event $event): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper(trim($request->code));

        // Find the ticket
        $ticket = Ticket::where('code', $code)->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
                'type' => 'error',
            ]);
        }

        // Verify ticket is for this event
        if ($ticket->order->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket is for a different event.',
                'type' => 'warning',
            ]);
        }

        // Check if already checked in
        if ($ticket->status === TicketStatus::CheckedIn) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket has already been checked in.',
                'type' => 'warning',
                'name' => $ticket->getHolderName(),
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ]);
        }

        // Check if ticket can be checked in
        if (! $ticket->canCheckIn()) {
            return response()->json([
                'success' => false,
                'message' => 'This ticket cannot be checked in: '.$ticket->status->label(),
                'type' => 'error',
            ]);
        }

        // Check in the ticket
        $ticket->checkIn($request->user());

        // Get updated stats
        $checkedInCount = $event->tickets()
            ->where('tickets.status', TicketStatus::CheckedIn)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Ticket checked in successfully.',
            'type' => 'success',
            'name' => $ticket->getHolderName(),
            'stats' => [
                'sold' => $event->tickets_sold ?? 0,
                'checked_in' => $checkedInCount,
            ],
        ]);
    }

    /**
     * Get recent check-ins for an event.
     */
    public function recentCheckIns(Request $request, Event $event): JsonResponse
    {
        $limit = $request->query('limit', 10);

        $checkIns = $event->tickets()
            ->where('tickets.status', TicketStatus::CheckedIn)
            ->orderByDesc('checked_in_at')
            ->limit($limit)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'name' => $ticket->getHolderName(),
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ]);

        return response()->json(['check_ins' => $checkIns]);
    }
}
