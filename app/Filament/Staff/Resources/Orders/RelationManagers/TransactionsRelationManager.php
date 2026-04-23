<?php

namespace App\Filament\Staff\Resources\Orders\RelationManagers;

use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\TransactionState\Cancelled;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $recordTitleAttribute = 'id';


    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->headerActions([
                Action::make('amount_due')
                    ->label('$' . number_format($this->ownerRecord->outstandingAmount() / 100, 2) . ' due')
                    ->color('black')
                    ->link()
                    ->disabled(),
            ])
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('currency')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'payment' => 'info',
                        'refund' => 'warning',
                        'fee' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => ucfirst($state)),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->color(fn(Transaction $record) => match (true) {
                        $record->isRefund() => 'danger',
                        $record->status instanceof Cancelled => 'gray',
                        $record->isPayment() => 'success',
                        default => 'success',
                    }),

                TextColumn::make('status')
                    ->tooltip(fn(Transaction $record) => match (true) {
                        $record->status instanceof Cancelled => $record->status->getDescription(),
                        $record->status instanceof Pending => 'Pending since ' . $record->created_at->toDayDateTimeString(),
                        $record->status instanceof Cleared => 'Cleared at ' . $record->cleared_at?->toDayDateTimeString(),
                        default => null,
                    })
                    ->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([]);
    }
}
