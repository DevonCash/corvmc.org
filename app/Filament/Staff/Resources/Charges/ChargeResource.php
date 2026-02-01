<?php

namespace App\Filament\Staff\Resources\Charges;

use App\Filament\Staff\Resources\Charges\Pages\ListCharges;
use App\Filament\Staff\Resources\Charges\Pages\ViewCharge;
use App\Models\User;
use BackedEnum;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\Finance\Models\Charge;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChargeResource extends Resource
{
    protected static ?string $model = Charge::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Charges';

    protected static ?int $navigationSort = 50;

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

        // Staff who can manage reservations or events can view charges
        return $user->hasRole(['admin', 'staff', 'practice space manager', 'production manager']);
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false; // Charges are created through other flows
    }

    public static function canEdit($record): bool
    {
        return false; // Use actions instead
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getRecordTitle($record): string
    {
        return "Charge #{$record->id}";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->description(fn (Charge $record) => $record->user?->email),

                TextColumn::make('chargeable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('amount')
                    ->label('Gross')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('net_amount')
                    ->label('Net')
                    ->money('USD')
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
                        'reservation' => 'Reservation',
                        'equipment_loan' => 'Equipment Loan',
                    ]),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),

                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('tabler-coin')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Charge $record) => $record->status->isPending())
                    ->action(function (Charge $record) {
                        $record->markAsPaid('manual', null, 'Marked paid by staff');
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Charge Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Charge #'),

                                TextEntry::make('status')
                                    ->badge(),

                                TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->badge()
                                    ->placeholder('—'),
                            ]),
                    ]),

                Fieldset::make('User')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Name'),

                                TextEntry::make('user.email')
                                    ->label('Email')
                                    ->copyable(),
                            ]),
                    ]),

                Fieldset::make('Chargeable')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('chargeable_type')
                                    ->label('Type')
                                    ->formatStateUsing(fn (string $state) => class_basename($state)),

                                TextEntry::make('chargeable_id')
                                    ->label('ID'),
                            ]),
                    ]),

                Fieldset::make('Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Gross Amount')
                                    ->money('USD'),

                                TextEntry::make('credits_applied')
                                    ->label('Credits Applied')
                                    ->formatStateUsing(function ($state) {
                                        if (empty($state)) {
                                            return 'None';
                                        }
                                        return collect($state)
                                            ->map(fn ($amount, $type) => "{$type}: {$amount}")
                                            ->implode(', ');
                                    }),

                                TextEntry::make('net_amount')
                                    ->label('Net Amount')
                                    ->money('USD')
                                    ->weight('bold'),
                            ]),
                    ]),

                Fieldset::make('Payment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime()
                                    ->placeholder('Not paid'),

                                TextEntry::make('stripe_session_id')
                                    ->label('Stripe Session')
                                    ->copyable()
                                    ->placeholder('—'),
                            ]),
                    ]),

                Fieldset::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Fieldset::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('No notes'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharges::route('/'),
            'view' => ViewCharge::route('/{record}'),
        ];
    }
}
