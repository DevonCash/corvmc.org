<?php

namespace App\Filament\Staff\Resources\Orders\Pages;

use App\Filament\Staff\Resources\Orders\OrderResource;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),

            'unsettled' => Tab::make()
                ->icon('tabler-alert-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereState('status', Pending::class)
                    ->whereHas('transactions', fn ($q) => $q
                        ->where('type', 'payment')
                        ->whereState('status', TransactionPending::class)
                    )
                ),
        ];
    }
}
