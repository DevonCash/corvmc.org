<?php

namespace App\Livewire;

use App\Models\User;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\TicketOrder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Member dashboard component to view purchased tickets.
 */
class MyTickets extends Component
{
    public bool $showPastEvents = false;

    /**
     * Get upcoming ticket orders for the current user.
     */
    public function getUpcomingOrders()
    {
        /** @var User $user */
        $user = Auth::user();

        return TicketOrder::where('user_id', $user->id)
            ->where('status', TicketOrderStatus::Completed)
            ->whereHas('event', function ($query) {
                $query->where('start_datetime', '>=', now());
            })
            ->with(['event', 'tickets'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get past ticket orders for the current user.
     */
    public function getPastOrders()
    {
        /** @var User $user */
        $user = Auth::user();

        return TicketOrder::where('user_id', $user->id)
            ->whereIn('status', [TicketOrderStatus::Completed, TicketOrderStatus::Refunded])
            ->whereHas('event', function ($query) {
                $query->where('start_datetime', '<', now());
            })
            ->with(['event', 'tickets'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function togglePastEvents(): void
    {
        $this->showPastEvents = !$this->showPastEvents;
    }

    public function render()
    {
        return view('livewire.my-tickets', [
            'upcomingOrders' => $this->getUpcomingOrders(),
            'pastOrders' => $this->showPastEvents ? $this->getPastOrders() : collect(),
        ]);
    }
}
