<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use App\Filament\Staff\Resources\Orders\Actions;
use App\Filament\Staff\Resources\Orders\OrderResource;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('lineItems', 'transactions'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->width(0)
                    ->sortable(),

                IconColumn::make('status')
                    ->label('')
                    ->width(0)
                    ->tooltip(fn (Order $record) => $record->status?->getLabel()),

                TextColumn::make('primary_product_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (Order $record): ?string {
                        $lineItem = $record->lineItems->first(fn ($li) => ! $li->isDiscount());

                        return $lineItem?->product_type;
                    })
                    ->formatStateUsing(fn (?string $state) => $state
                        ? ucwords(str_replace('_', ' ', $state))
                        : '—'
                    ),

                TextColumn::make('payment_rail')
                    ->label('Rail')
                    ->badge()
                    ->getStateUsing(function (Order $record): ?string {
                        $paymentTxns = $record->transactions->where('type', 'payment');
                        if ($paymentTxns->isEmpty()) {
                            return null;
                        }

                        return $paymentTxns->pluck('currency')->unique()->sort()->implode(', ');
                    })
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords($state) : '—')
                    ->color(fn (?string $state) => match ($state) {
                        'cash' => 'warning',
                        'stripe' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->color(fn (Order $record) => $record->status instanceof Pending ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Pending::$name => 'Pending',
                        Completed::$name => 'Completed',
                        Comped::$name => 'Comped',
                        Refunded::$name => 'Refunded',
                        Cancelled::$name => 'Cancelled',
                    ])
                    ->multiple(),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\ViewAction::make(),
                    Actions\MarkPaidAction::make(),
                    Actions\CollectCashAction::make(),
                    Actions\CompOrderAction::make(),
                    Actions\CancelOrderAction::make(),
                    Actions\RefundOrderAction::make(),
                ]),
            ]);
    }
}
