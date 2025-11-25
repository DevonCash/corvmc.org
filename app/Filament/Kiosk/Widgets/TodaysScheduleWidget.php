<?php

namespace App\Filament\Kiosk\Widgets;

use App\Models\Reservation;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TodaysScheduleWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 1;


    public function table(Table $table): Table
    {
        return $table
            ->searchable(false)
            ->query(
                Reservation::query()
                    ->whereDate('reserved_at', today())
                    ->with(['reservable'])
                    ->orderBy('reserved_at')
            )
            ->columns([
                Tables\Columns\IconColumn::make('status')
                ->grow(false),
                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Time')
                    ->dateTime('g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_until')
                    ->label('Until')
                    ->dateTime('g:i A'),
                Tables\Columns\TextColumn::make('reservable.name')
                    ->label('Reserved By')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('acceptPayment')
                    ->label('Payment')
                    ->visible(fn (Reservation $record): bool => $record->isUnpaid())
                    ->button(),
                Action::make('checkIn')
                    ->label('Check In')
                    ->button()
                    ->url(fn (Reservation $record): string => \App\Filament\Kiosk\Pages\CheckInMember::getUrl(['reservation_id' => $record->id])),
                Action::make('checkOut')
                    ->label('Check Out')
                    ->button()
                    ->url(fn (Reservation $record): string => \App\Filament\Kiosk\Pages\CheckOutMember::getUrl(['reservation_id' => $record->id]))
            ])
            ->paginated(false)
            ->heading("Today's Schedule");
    }
}
