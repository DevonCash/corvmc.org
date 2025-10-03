<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Models\Reservation;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reservation Details')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Member'),

                        TextEntry::make('production.title')
                            ->label('Production')
                            ->placeholder('No production linked'),

                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'confirmed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('time_range')
                            ->label('Time Range')
                            ->state(function (Reservation $record): string {
                                return $record->time_range;
                            }),
                    ])
                    ->columns(2),

                Section::make('Duration & Cost')
                    ->schema([
                        TextEntry::make('duration')
                            ->label('Duration')
                            ->state(function (Reservation $record): string {
                                return number_format($record->duration, 1).' hours';
                            }),

                        TextEntry::make('cost_display')
                            ->label('Total Cost')
                            ->state(function (Reservation $record): string {
                                return $record->cost_display;
                            }),

                        TextEntry::make('free_hours_used')
                            ->label('Free Hours Used')
                            ->formatStateUsing(fn (float $state): string => number_format($state, 1).' hours')
                            ->color('success'),

                        TextEntry::make('hours_used')
                            ->label('Total Hours')
                            ->formatStateUsing(fn (float $state): string => number_format($state, 1).' hours'),
                    ])
                    ->columns(2),

                Section::make('Payment Information')
                    ->schema([
                        TextEntry::make('payment_status')
                            ->label('Payment Status')
                            ->badge()
                            ->formatStateUsing(function (string $state, Reservation $record): string {
                                return $record->payment_status_badge['label'];
                            })
                            ->color(function (string $state, Reservation $record): string {
                                return $record->payment_status_badge['color'];
                            }),

                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->placeholder('Not specified')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Not specified'),

                        TextEntry::make('paid_at')
                            ->label('Paid Date')
                            ->dateTime()
                            ->placeholder('Not paid'),

                        TextEntry::make('payment_notes')
                            ->label('Payment Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(fn (Reservation $record): bool => $record->cost->isPositive()),

                Section::make('Additional Information')
                    ->schema([
                        IconEntry::make('is_recurring')
                            ->label('Recurring Reservation')
                            ->boolean(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('System Information')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
