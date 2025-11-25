<?php

namespace App\Filament\Kiosk\Widgets;

use App\Models\CheckIn;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CurrentlyCheckedInWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CheckIn::query()
                    ->currentlyCheckedIn()
                    ->with(['user', 'checkable'])
                    ->orderBy('checked_in_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In')
                    ->dateTime('g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('checkable_type')
                    ->label('Activity')
                    ->formatStateUsing(function ($state, CheckIn $record) {
                        return match ($state) {
                            'App\\Models\\Reservation' => 'Practice • '.$record->checkable->time_range,
                            'App\\Models\\VolunteerShift' => 'Volunteering',
                            'App\\Models\\Event' => 'Event • '.$record->checkable->title,
                            default => class_basename($state),
                        };
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->paginated(false)
            ->heading('Currently Checked In');
    }
}
