<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\TicketOrder;
use Filament\Pages\Page;

class MyTickets extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-ticket';

    protected string $view = 'filament.pages.my-tickets';

    protected static string|\UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'My Tickets';

    protected static ?string $slug = 'my-tickets';

    public function getUpcomingOrders()
    {
        /** @var User $user */
        $user = User::me();

        return TicketOrder::where('user_id', $user->id)
            ->where('status', TicketOrderStatus::Completed)
            ->whereHas('event', function ($query) {
                $query->where('start_datetime', '>=', now());
            })
            ->with(['event', 'tickets'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPastOrders()
    {
        /** @var User $user */
        $user = User::me();

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

    public static function shouldRegisterNavigation(): bool
    {
        // Only show navigation if user has any ticket orders
        /** @var User|null $user */
        $user = User::me();

        if (!$user) {
            return false;
        }

        return TicketOrder::where('user_id', $user->id)
            ->where('status', TicketOrderStatus::Completed)
            ->exists();
    }
}
