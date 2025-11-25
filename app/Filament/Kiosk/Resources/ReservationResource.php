<?php

namespace App\Filament\Kiosk\Resources;

use App\Actions\CheckIns\CheckInUser;
use App\Actions\Reservations\CancelReservation;
use App\Filament\Kiosk\Resources\ReservationResource\Pages;
use App\Models\Reservation;
use BackedEnum;
use Filament\Actions\{Action, ViewAction};
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Find Reservation';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('reservable.name')
                    ->label('Reserved By')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('reserved_at')
                    ->label('Start Time')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('reserved_until')
                    ->label('End Time')
                    ->disabled(),

                Forms\Components\TextInput::make('hours_used')
                    ->label('Duration (hours)')
                    ->disabled(),

                Forms\Components\TextInput::make('status')
                    ->disabled(),

                Forms\Components\TextInput::make('cost_display')
                    ->label('Cost')
                    ->disabled(),

                Forms\Components\Textarea::make('notes')
                    ->disabled()
                    ->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Date & Time')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->size(TextSize::Large),

                Tables\Columns\TextColumn::make('reservable.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable()
                    ->size(TextSize::Medium),

                Tables\Columns\TextColumn::make('hours_used')
                    ->label('Hours')
                    ->suffix(' hrs'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state->value) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('reserved_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn($query) => $query->whereDate('reserved_at', today()))
                    ->toggle(),

                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming')
                    ->query(fn($query) => $query->where('reserved_at', '>=', now()))
                    ->toggle()
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->size(Size::Large),

                Action::make('checkin')
                    ->label('Check In')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->size(Size::Large)
                    ->visible(
                        fn(Reservation $record) =>
                        $record->status->value === 'confirmed' &&
                            $record->reserved_at->isToday()
                    )
                    ->requiresConfirmation()
                    ->action(function (Reservation $record) {
                        try {
                            CheckInUser::run($record->reservable, $record);

                            Notification::make()
                                ->success()
                                ->title('Checked In')
                                ->body("{$record->reservable->name} has been checked in for their reservation.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Check-In Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->size(Size::Large)
                    ->visible(
                        fn(Reservation $record) =>
                        in_array($record->status->value, ['confirmed', 'pending'])
                    )
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to cancel this reservation?')
                    ->schema([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Reason (optional)')
                            ->rows(2),
                    ])
                    ->action(function (Reservation $record, array $data) {
                        try {
                            CancelReservation::run($record, $data['cancellation_reason'] ?? null);

                            Notification::make()
                                ->success()
                                ->title('Reservation Cancelled')
                                ->body('The reservation has been cancelled.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cancellation Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'view' => Pages\ViewReservation::route('/{record}'),
        ];
    }
}
