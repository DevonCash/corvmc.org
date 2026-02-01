<?php

namespace App\Filament\Staff\Resources\TicketOrders;

use App\Filament\Shared\Actions\Action;
use App\Filament\Shared\Actions\ViewAction;
use App\Filament\Staff\Resources\Events\EventResource;
use App\Filament\Staff\Resources\TicketOrders\Pages\ListTicketOrders;
use App\Filament\Staff\Resources\TicketOrders\Pages\ViewTicketOrder;
use App\Models\User;
use BackedEnum;
use CorvMC\Events\Actions\Tickets\RefundTicketOrder;
use CorvMC\Events\Enums\TicketOrderStatus;
use CorvMC\Events\Models\TicketOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\ParentResourceRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\RepeatableEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketOrderResource extends Resource
{
    protected static ?string $model = TicketOrder::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-ticket';

    protected static ?string $navigationLabel = 'Ticket Orders';

    protected static bool $shouldRegisterNavigation = false;

    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return EventResource::asParent()
            ->relationship('ticketOrders');
    }

    public static function canViewAny(): bool
    {
        return User::me()?->can('manage events') ?? false;
    }

    public static function canView($record): bool
    {
        return User::me()?->can('manage events') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Orders are created through checkout flow
    }

    public static function canEdit($record): bool
    {
        return false; // Orders are immutable
    }

    public static function canDelete($record): bool
    {
        return false; // Orders cannot be deleted, only refunded
    }

    public static function getRecordTitle($record): string
    {
        return "Order #{$record->id}";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Purchaser')
                    ->searchable()
                    ->description(fn(TicketOrder $record) => $record->email),

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

                TextColumn::make('payment_method')
                    ->label('Payment')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Ordered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TicketOrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('refund')
                    ->label('Refund')
                    ->icon('tabler-receipt-refund')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Refund Order')
                    ->modalDescription(
                        fn(TicketOrder $record) =>
                        "Are you sure you want to refund {$record->quantity} ticket(s) for \${$record->total->getAmount()->toFloat()}? This action cannot be undone."
                    )
                    ->visible(fn(TicketOrder $record) => $record->canRefund())
                    ->action(function (TicketOrder $record) {
                        RefundTicketOrder::run($record, 'Refunded by staff');
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Order Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('Order #'),

                                TextEntry::make('uuid')
                                    ->label('UUID')
                                    ->copyable(),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(TicketOrderStatus $state): string => $state->color()),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Purchaser Name'),

                                TextEntry::make('email')
                                    ->label('Purchaser Email')
                                    ->copyable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('User Account')
                                    ->placeholder('Guest checkout'),

                                TextEntry::make('payment_method')
                                    ->label('Payment Method')
                                    ->badge(),
                            ]),
                    ]),

                Fieldset::make('Event')
                    ->schema([
                        TextEntry::make('event.title')
                            ->label('Event'),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('event.start_datetime')
                                    ->label('Date')
                                    ->dateTime(),

                                TextEntry::make('event.venue_name')
                                    ->label('Venue'),
                            ]),
                    ]),

                Fieldset::make('Pricing')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Quantity'),

                                TextEntry::make('unit_price')
                                    ->label('Unit Price')
                                    ->money('USD'),

                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('USD'),

                                TextEntry::make('discount')
                                    ->label('Discount')
                                    ->money('USD'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('fees')
                                    ->label('Processing Fees')
                                    ->money('USD'),

                                TextEntry::make('covers_fees')
                                    ->label('Covers Fees')
                                    ->badge()
                                    ->color(fn(bool $state) => $state ? 'success' : 'gray'),

                                TextEntry::make('total')
                                    ->label('Total Charged')
                                    ->money('USD')
                                    ->weight('bold'),
                            ]),
                    ]),

                Fieldset::make('Tickets')
                    ->schema([
                        RepeatableEntry::make('tickets')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('Code')
                                            ->copyable()
                                            ->weight('bold'),

                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn($state) => $state->color()),

                                        TextEntry::make('attendee_name')
                                            ->label('Attendee')
                                            ->placeholder('—'),

                                        TextEntry::make('checked_in_at')
                                            ->label('Checked In')
                                            ->dateTime()
                                            ->placeholder('Not checked in'),
                                    ]),
                            ])
                            ->columns(1),
                    ]),

                Fieldset::make('Timestamps')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->dateTime(),

                                TextEntry::make('completed_at')
                                    ->label('Completed')
                                    ->dateTime()
                                    ->placeholder('—'),

                                TextEntry::make('refunded_at')
                                    ->label('Refunded')
                                    ->dateTime()
                                    ->placeholder('—'),

                                TextEntry::make('is_door_sale')
                                    ->label('Door Sale')
                                    ->badge()
                                    ->color(fn(bool $state) => $state ? 'warning' : 'gray'),
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
            'index' => ListTicketOrders::route('/'),
            'view' => ViewTicketOrder::route('/{record}'),
        ];
    }
}
