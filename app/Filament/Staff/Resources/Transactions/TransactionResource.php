<?php

namespace App\Filament\Staff\Resources\Transactions;

use App\Filament\Staff\Resources\Transactions\Pages\ListTransactions;
use App\Models\User;
use BackedEnum;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\Finance\States\TransactionState\Cleared;
use CorvMC\Finance\States\TransactionState\Failed;
use CorvMC\Finance\States\TransactionState\Pending;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-transfer';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?int $navigationSort = 46;

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
        return "Transaction #{$record->id}";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order', 'user'))
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('order_id')
                    ->label('Order')
                    ->url(fn (Transaction $record) => route('filament.staff.resources.orders.view', $record->order_id))
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->description(fn (Transaction $record) => $record->user?->email),

                TextColumn::make('currency')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'payment' => 'info',
                        'refund' => 'warning',
                        'fee' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->sortable()
                    ->color(fn (Transaction $record) => $record->isRefund() ? 'danger' : 'success'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('terminal_at')
                    ->label('Resolved')
                    ->getStateUsing(fn (Transaction $record) => $record->cleared_at ?? $record->cancelled_at ?? $record->failed_at)
                    ->dateTime()
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("COALESCE(cleared_at, cancelled_at, failed_at) {$direction}"))
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Pending::$name => 'Pending',
                        Cleared::$name => 'Cleared',
                        TransactionCancelled::$name => 'Cancelled',
                        Failed::$name => 'Failed',
                    ])
                    ->multiple(),

                SelectFilter::make('currency')
                    ->options([
                        'stripe' => 'Stripe',
                        'cash' => 'Cash',
                    ]),

                SelectFilter::make('type')
                    ->options([
                        'payment' => 'Payment',
                        'refund' => 'Refund',
                        'fee' => 'Fee',
                    ]),
            ])
            ->recordActions([
                Action::make('settle')
                    ->label('Mark Paid')
                    ->icon('tabler-coin')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will settle the transaction as a cash payment.')
                    ->visible(fn (Transaction $record) => $record->status instanceof Pending && $record->currency === 'cash')
                    ->action(function (Transaction $record) {
                        Finance::settle($record);
                        Notification::make()->title('Transaction settled')->success()->send();
                    }),

                Action::make('void')
                    ->label('Void')
                    ->icon('tabler-x')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This will cancel the pending transaction.')
                    ->visible(fn (Transaction $record) => $record->status instanceof Pending)
                    ->action(function (Transaction $record) {
                        $record->status->transitionTo(TransactionCancelled::class);
                        $record->update(['cancelled_at' => now()]);
                        Notification::make()->title('Transaction voided')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
        ];
    }
}
