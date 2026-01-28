<?php

namespace App\Filament\Staff\Resources\RecurringReservations\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class RecurringReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recurring Pattern')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Select::make('frequency')
                            ->label('Frequency')
                            ->options([
                                'WEEKLY' => 'Weekly',
                                'MONTHLY' => 'Monthly',
                            ])
                            ->default('WEEKLY')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('recurrence_rule', null)),

                        Select::make('interval')
                            ->label('Repeat Every')
                            ->options([
                                1 => '1',
                                2 => '2',
                                3 => '3',
                                4 => '4',
                            ])
                            ->default(1)
                            ->required()
                            ->reactive()
                            ->suffix(fn (Get $get) => $get('frequency') === 'WEEKLY' ? 'week(s)' : 'month(s)'),

                        CheckboxList::make('by_day')
                            ->label('On Days')
                            ->options([
                                'MO' => 'Monday',
                                'TU' => 'Tuesday',
                                'WE' => 'Wednesday',
                                'TH' => 'Thursday',
                                'FR' => 'Friday',
                                'SA' => 'Saturday',
                                'SU' => 'Sunday',
                            ])
                            ->visible(fn (Get $get) => $get('frequency') === 'WEEKLY')
                            ->required(fn (Get $get) => $get('frequency') === 'WEEKLY')
                            ->columns(3)
                            ->reactive(),

                        DatePicker::make('series_start_date')
                            ->label('Start Date')
                            ->required()
                            ->minDate(now()->addDay())
                            ->reactive(),

                        DatePicker::make('series_end_date')
                            ->label('End Date (optional)')
                            ->helperText('Leave blank for ongoing reservation')
                            ->minDate(fn (Get $get) => $get('series_start_date') ?? now())
                            ->reactive(),
                    ])
                    ->columns(2),

                Section::make('Time and Duration')
                    ->schema([
                        TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->seconds(false)
                            ->reactive(),

                        TimePicker::make('end_time')
                            ->label('End Time')
                            ->required()
                            ->seconds(false)
                            ->reactive()
                            ->after('start_time'),

                        TextInput::make('duration_minutes')
                            ->label('Duration (minutes)')
                            ->disabled()
                            ->dehydrated(true)
                            ->default(fn (Get $get) => self::calculateDuration($get('start_time'), $get('end_time'))),
                    ])
                    ->columns(3),

                Section::make('Settings')
                    ->schema([
                        Select::make('max_advance_days')
                            ->label('Generate Instances')
                            ->options([
                                30 => '30 days ahead',
                                60 => '60 days ahead',
                                90 => '90 days ahead (default)',
                                120 => '120 days ahead',
                                180 => '180 days ahead',
                            ])
                            ->default(90)
                            ->required()
                            ->helperText('How far in advance to automatically create reservation instances'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Preview')
                    ->schema([
                        TextEntry::make('pattern_preview')
                            ->label('Recurrence Pattern')
                            ->state(function (Get $get) {
                                $frequency = $get('frequency');
                                $interval = $get('interval') ?? 1;
                                $byDay = $get('by_day') ?? [];

                                if (! $frequency) {
                                    return 'Select options above to preview pattern';
                                }

                                if ($frequency === 'WEEKLY') {
                                    $days = array_map(fn ($d) => ['MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun'][$d] ?? $d, $byDay);
                                    $dayStr = empty($days) ? 'no days selected' : implode(', ', $days);

                                    if ($interval == 1) {
                                        return "Every week on {$dayStr}";
                                    }

                                    return "Every {$interval} weeks on {$dayStr}";
                                }

                                if ($interval == 1) {
                                    return 'Every month';
                                }

                                return "Every {$interval} months";
                            }),
                    ])
                    ->visible(fn (Get $get) => $get('frequency') !== null),
            ]);
    }

    protected static function calculateDuration(?string $start, ?string $end): ?int
    {
        if (! $start || ! $end) {
            return null;
        }

        try {
            $startTime = Carbon::parse($start);
            $endTime = Carbon::parse($end);

            return $startTime->diffInMinutes($endTime);
        } catch (\Exception $e) {
            Log::error($e);

            return null;
        }
    }
}
