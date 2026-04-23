<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
            ->modifyQueryUsing(fn ($query) => $query->with('lineItems'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

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

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD', divideBy: 100)
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('USD', divideBy: 100)
                    ->getStateUsing(fn (Order $record) => $record->paidAmount()),

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
            ->recordActions([
                Actions\ViewAction::make()
                    ->url(fn (Order $record) => route('filament.staff.resources.orders.view', $record)),

                \App\Filament\Staff\Resources\Orders\Actions\MarkPaidAction::make(),
                \App\Filament\Staff\Resources\Orders\Actions\CollectCashAction::make(),
                \App\Filament\Staff\Resources\Orders\Actions\CompOrderAction::make(),
                \App\Filament\Staff\Resources\Orders\Actions\CancelOrderAction::make(),
                \App\Filament\Staff\Resources\Orders\Actions\RefundOrderAction::make(),
            ]);
    }
}
