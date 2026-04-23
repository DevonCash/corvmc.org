<?php

namespace App\Filament\Staff\Resources\Orders\RelationManagers;

use App\Filament\Actions\ViewModelAction;
use CorvMC\Finance\Models\LineItem;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LineItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lineItems';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->headerActions([
                Action::make('order_total')
                    ->label(fn() => 'Total: $' . number_format($this->ownerRecord->total_amount / 100, 2))
                    ->color('black')
                    ->link()
                    ->disabled(),
            ])
            ->columns([
                TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn($state) => number_format((float) $state, 2)),

                TextColumn::make('unit')
                    ->label('Unit'),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('USD', divideBy: 100),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->color(fn(int $state) => $state < 0 ? 'success' : null)
            ])
            ->defaultSort('id')
            ->recordActions([
                ViewModelAction::make(fn(LineItem $record) => $record->product()),
            ]);
    }
}
