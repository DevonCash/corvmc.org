<?php

namespace App\Filament\Staff\Resources\Events\RelationManagers;

use App\Filament\Staff\Resources\TicketOrders\TicketOrderResource;
use CorvMC\Events\Enums\TicketOrderStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketOrders';

    protected static ?string $relatedResource = TicketOrderResource::class;

    protected static ?string $title = 'Ticket Orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Purchaser')
                    ->searchable()
                    ->description(fn($record) => $record->email),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(TicketOrderStatus $state): string => $state->color()),

                TextColumn::make('created_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TicketOrderStatus::class),
            ]);
    }
}
