<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Filament\Tables\Columns\MorphTypeColumn;

class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Charges are typically created automatically, not manually
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                MorphTypeColumn::make('chargeable')
                    ->label('Type'),

                TextColumn::make('amount')
                    ->label('Gross')
                    ->sortable(),

                TextColumn::make('net_amount')
                    ->label('Net')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime()
                    ->sortable()
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
                    ->options(ChargeStatus::class)
                    ->multiple(),

                SelectFilter::make('chargeable_type')
                    ->label('Type')
                    ->options([
                        'rehearsal_reservation' => 'Reservation',
                        'equipment_loan' => 'Equipment Loan',
                        'event' => 'Event',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make(),

                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('tabler-coin')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Charge $record) => $record->status->isPending())
                    ->action(function (Charge $record) {
                        $record->markAsPaid('manual', null, null, 'Marked paid by staff');
                        Notification::make()->title('Charge marked as paid')->success()->send();
                    }),

                Action::make('markComped')
                    ->label('Comp')
                    ->icon('tabler-gift')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Charge $record) => $record->status->isPending())
                    ->action(function (Charge $record) {
                        $record->markAsComped('Comped by staff');
                        Notification::make()->title('Charge comped')->success()->send();
                    }),

                Action::make('markRefunded')
                    ->label('Refund')
                    ->icon('tabler-receipt-refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Charge $record) => $record->status->isPaid())
                    ->action(function (Charge $record) {
                        $record->markAsRefunded('Refunded by staff');
                        Notification::make()->title('Charge refunded')->success()->send();
                    }),
            ]);
    }
}
