<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Actions\Reservations\GetAvailableTimeSlotsForDate;
use App\Actions\Reservations\GetValidEndTimesForDate;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Schema;

class MemberReservationEditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    DatePicker::make('reservation_date')
                        ->label('Date')
                        ->required()
                        ->live()
                        ->disabled(fn ($record) => $record->payment_status->isPaid())
                        ->columnSpan(2)
                        ->default(fn ($record) => $record?->reserved_at?->toDateString())
                        ->minDate(now()->toDateString()),

                    Select::make('start_time')
                        ->grow(false)
                        ->label('Start Time')
                        ->options(function ($get, $record, $state) {
                            $date = $get('reservation_date') ?? $record?->reserved_at?->toDateString();
                            if (! $date) {
                                return [];
                            }
                            $options = GetAvailableTimeSlotsForDate::run(Carbon::parse($date));

                            // Ensure current value is in options when editing (with proper 12-hour format label)
                            if ($state && ! isset($options[$state])) {
                                $time = Carbon::parse($state);
                                $options[$state] = $time->format('g:i A');
                            }

                            return $options;
                        })
                        ->required()
                        ->live()
                        ->searchable(),

                    Select::make('end_time')
                        ->label('End Time')
                        ->grow(false)
                        ->options(function ($get, $record, $state) {
                            $date = $get('reservation_date') ?? $record?->reserved_at?->toDateString();
                            $startTime = $get('start_time') ?? $record?->reserved_at?->format('H:i');
                            if (! $date || ! $startTime) {
                                return [];
                            }
                            $options = GetValidEndTimesForDate::run(Carbon::parse($date), $startTime);

                            // Ensure current value is in options when editing (with proper 12-hour format label)
                            if ($state && ! isset($options[$state])) {
                                $time = Carbon::parse($state);
                                $options[$state] = $time->format('g:i A');
                            }

                            return $options;
                        })
                        ->required()
                        ->disabled(fn ($get) => ! $get('start_time'))
                        ->searchable(),
                ]),

                Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Any special requests or notes about your reservation')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
