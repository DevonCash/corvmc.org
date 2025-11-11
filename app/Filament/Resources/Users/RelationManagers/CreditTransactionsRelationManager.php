<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Actions\Credits\AdjustCredits;
use App\Models\Reservation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CreditTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditTransactions';

    protected static ?string $title = 'User Credits';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        // Read-only - no form needed
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('credit_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'free_hours' => 'Free Hours',
                        'equipment_credits' => 'Equipment',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'free_hours' => 'info',
                        'equipment_credits' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Change')
                    ->formatStateUsing(function (int $state): string {
                        $hours = Reservation::blocksToHours(abs($state));
                        $sign = $state >= 0 ? '+' : '';

                        return "{$sign}{$state} blocks ({$hours}h)";
                    })
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->formatStateUsing(function (int $state): string {
                        $hours = Reservation::blocksToHours($state);

                        return "{$state} blocks ({$hours}h)";
                    }),

                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly_reset' => 'Monthly Reset',
                        'monthly_allocation' => 'Monthly Allocation',
                        'reservation_usage' => 'Reservation Usage',
                        'reservation_cancellation' => 'Cancellation Refund',
                        'reservation_update' => 'Reservation Update',
                        'admin_adjustment' => 'Admin Adjustment',
                        'upgrade_adjustment' => 'Membership Upgrade',
                        'expiration' => 'Expiration',
                        default => ucwords(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'monthly_reset', 'monthly_allocation', 'upgrade_adjustment' => 'success',
                        'reservation_usage' => 'info',
                        'reservation_cancellation', 'reservation_update' => 'warning',
                        'admin_adjustment' => 'purple',
                        'expiration' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('credit_type')
                    ->options([
                        'free_hours' => 'Free Hours',
                        'equipment_credits' => 'Equipment Credits',
                    ]),

                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'monthly_reset' => 'Monthly Reset',
                        'monthly_allocation' => 'Monthly Allocation',
                        'reservation_usage' => 'Reservation Usage',
                        'reservation_cancellation' => 'Cancellation Refund',
                        'reservation_update' => 'Reservation Update',
                        'admin_adjustment' => 'Admin Adjustment',
                        'upgrade_adjustment' => 'Membership Upgrade',
                        'expiration' => 'Expiration',
                    ]),

                Tables\Filters\Filter::make('additions_only')
                    ->label('Additions Only')
                    ->query(fn ($query) => $query->where('amount', '>', 0)),

                Tables\Filters\Filter::make('deductions_only')
                    ->label('Deductions Only')
                    ->query(fn ($query) => $query->where('amount', '<', 0)),
            ])
            ->headerActions([
                AdjustCredits::filamentAction(),
            ])
            ->recordActions([
                // No edit/delete actions - transactions are immutable
            ])
            ->toolbarActions([
                // No bulk actions - transactions are immutable
            ]);
    }
}
