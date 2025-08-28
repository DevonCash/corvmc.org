<?php

namespace App\Filament\Resources\BandProfiles\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BandProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->imageSize(60)
                    ->grow(false),

                TextColumn::make('name')
                    ->label('Band')
                    ->grow(condition: false)
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(function ($record) {
                        $parts = [];

                        // Add location if available
                        if ($record->hometown) {
                            $parts[] = $record->hometown;
                        }

                        return implode(' • ', $parts);
                    }),

                SpatieTagsColumn::make('genres')
                    ->label('Genres')
                    ->type('genre')
                    ->grow(true)
                    ->separator(', '),

                TextColumn::make('activity')
                    ->label('Activity')
                    ->getStateUsing(fn($record) => 'Active ' . $record->updated_at->diffForHumans())
                    ->color('gray')
                    ->size('sm'),
            ])
            ->filters([
                SelectFilter::make('genres')
                    ->label('Musical Genre')
                    ->multiple()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'genre')
                            ->pluck('name', 'name')
                            ->sort()
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (! empty($data['values'])) {
                            return $query->withAnyTags($data['values'], 'genre');
                        }

                        return $query;
                    }),

                SelectFilter::make('hometown')
                    ->label('Location')
                    ->options(function () {
                        return \App\Models\BandProfile::whereNotNull('hometown')
                            ->distinct()
                            ->pluck('hometown', 'hometown')
                            ->sort()
                            ->toArray();
                    }),

                SelectFilter::make('has_members')
                    ->label('Band Status')
                    ->options([
                        'with_members' => 'Active Bands (with members)',
                        'seeking_members' => 'Seeking Members',
                    ])
                    ->query(function ($query, array $data) {
                        if (! empty($data['value'])) {
                            if ($data['value'] === 'with_members') {
                                return $query->has('members', '>', 1);
                            } elseif ($data['value'] === 'seeking_members') {
                                return $query->has('members', '<=', 1);
                            }
                        }

                        return $query;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
