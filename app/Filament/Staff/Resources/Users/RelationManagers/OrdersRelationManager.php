<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('tabler-coin')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->status instanceof Pending
                        && $record->transactions()->where('currency', 'cash')->whereState('status', TransactionPending::class)->exists()
                    )
                    ->action(function (Order $record) {
                        $record->transactions()
                            ->where('currency', 'cash')
                            ->whereState('status', TransactionPending::class)
                            ->each(fn ($txn) => Finance::settle($txn));

                        Notification::make()->title('Order marked as paid')->success()->send();
                    }),

                Action::make('comp')
                    ->label('Comp')
                    ->icon('tabler-gift')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->status instanceof Pending)
                    ->action(function (Order $record) {
                        Finance::comp($record);
                        Notification::make()->title('Order comped')->success()->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('tabler-x')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->status instanceof Pending)
                    ->action(function (Order $record) {
                        Finance::cancel($record);
                        Notification::make()->title('Order cancelled')->success()->send();
                    }),

                \App\Filament\Staff\Resources\Orders\Actions\RefundOrderAction::make(),
            ]);
    }
}
