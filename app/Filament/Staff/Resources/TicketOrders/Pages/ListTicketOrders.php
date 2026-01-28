<?php

namespace App\Filament\Staff\Resources\TicketOrders\Pages;

use App\Filament\Staff\Resources\TicketOrders\TicketOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListTicketOrders extends ListRecords
{
    protected static string $resource = TicketOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
