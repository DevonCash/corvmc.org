<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\Volunteering\Models\HourLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VolunteerHourLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'volunteerHourLogs';

    protected static ?string $title = 'Volunteer Hours';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['shift.position', 'shift.event', 'position'])->latest())
            ->columns([
                TextColumn::make('role')
                    ->label('Position')
                    ->getStateUsing(function (HourLog $record) {
                        if ($record->shift) {
                            return $record->shift->position->title ?? '—';
                        }

                        return $record->position->title ?? '—';
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('shift.position', fn ($q) => $q->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('position', fn ($q) => $q->where('title', 'like', "%{$search}%"));
                    }),

                TextColumn::make('event_name')
                    ->label('Event')
                    ->getStateUsing(fn (HourLog $record) => $record->shift?->event?->title)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—'),

                TextColumn::make('ended_at')
                    ->label('Ended')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('—'),

                TextColumn::make('minutes')
                    ->label('Minutes')
                    ->placeholder('—'),

                SpatieTagsColumn::make('tags')
                    ->limitList(3)
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
