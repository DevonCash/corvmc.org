<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Facades\ReservationService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ReservationEditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Reservation Details')
                    ->schema([
                        DatePicker::make('reservation_date')
                            ->label('Date')
                            ->required()
                            ->live()
                            ->default(fn ($record) => $record?->reserved_at?->toDateString())
                            ->minDate(now()->toDateString()),

                        Select::make('start_time')
                            ->label('Start Time')
                            ->options(function ($get, $record) {
                                $date = $get('reservation_date') ?? $record?->reserved_at?->toDateString();
                                if (!$date) {
                                    return [];
                                }
                                return ReservationService::getAvailableTimeSlotsForDate(Carbon::parse($date));
                            })
                            ->default(fn ($record) => $record?->reserved_at?->format('H:i'))
                            ->required()
                            ->live(),

                        Select::make('end_time')
                            ->label('End Time')
                            ->options(function ($get, $record) {
                                $date = $get('reservation_date') ?? $record?->reserved_at?->toDateString();
                                $startTime = $get('start_time') ?? $record?->reserved_at?->format('H:i');
                                if (!$date || !$startTime) {
                                    return [];
                                }
                                return ReservationService::getValidEndTimesForDateAndStart(Carbon::parse($date), $startTime);
                            })
                            ->default(fn ($record) => $record?->reserved_until?->format('H:i'))
                            ->required()
                            ->disabled(fn ($get) => !$get('start_time')),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Any special requests or notes about your reservation')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                Section::make('Admin Controls')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('confirmed')
                            ->required(),

                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'unpaid' => 'Unpaid',
                                'paid' => 'Paid',
                                'comped' => 'Comped',
                                'refunded' => 'Refunded',
                            ])
                            ->default('unpaid')
                            ->required(),
                    ])
                    ->visible(fn () => Auth::user()?->can('manage practice space'))
                    ->columnSpan(2),
            ]);
    }
}
