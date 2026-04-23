<?php

namespace App\Filament\Staff\Resources\Orders;

use App\Filament\Staff\Resources\Orders\Pages\ListOrders;
use App\Filament\Staff\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use BackedEnum;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Cancelled;
use CorvMC\Finance\States\OrderState\Completed;
use CorvMC\Finance\States\OrderState\Comped;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\OrderState\Refunded;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-receipt';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 45;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = User::me();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'staff', 'practice space manager', 'production manager']);
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getRecordTitle($record): string
    {
        return "Order #{$record->id}";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('lineItems', 'user', 'transactions'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->width(0)
                    ->sortable(),

                IconColumn::make('status')
                    ->label('')
                    ->width(0)
                    ->tooltip(fn(Order $record) => $record->status?->getLabel()),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->description(fn(Order $record) => $record->user?->email),

                TextColumn::make('primary_product_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (Order $record): ?string {
                        $lineItem = $record->lineItems->first(fn($li) => ! $li->isDiscount());

                        return $lineItem?->product_type;
                    })
                    ->formatStateUsing(
                        fn(?string $state) => $state
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
                    ->formatStateUsing(fn(?string $state) => $state ? ucwords($state) : '—')
                    ->color(fn(?string $state) => match ($state) {
                        'cash' => 'warning',
                        'stripe' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->color(fn(Order $record) => match (true) {
                        ($record->status instanceof Pending) => 'danger',
                        default => null,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('settled_at')
                    ->label('Settled')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->recordUrl(fn(Order $record) => static::getUrl('view', ['record' => $record]))
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
