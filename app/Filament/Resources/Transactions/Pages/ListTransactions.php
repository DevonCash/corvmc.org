<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\Actions\TransactionImportAction;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TransactionImportAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Transactions')
                ->badge(Transaction::count()),

            'recurring' => Tab::make('Recurring')
                ->badge(Transaction::where('type', 'recurring')->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'recurring')),

            'members' => Tab::make('Members')
                ->badge(Transaction::whereHas('user')->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('user')),

            'community' => Tab::make('Community Supporters')
                ->badge(Transaction::whereDoesntHave('user')->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDoesntHave('user')),
        ];
    }
}
