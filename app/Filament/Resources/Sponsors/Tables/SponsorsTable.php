<?php

namespace App\Filament\Resources\Sponsors\Tables;

use App\Models\Sponsor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SponsorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')
                    ->collection('logo')
                    ->size(60)
                    ->circular(false),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tier_name')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($record) => match ($record->tier) {
                        Sponsor::TIER_CRESCENDO => 'success',
                        Sponsor::TIER_RHYTHM => 'info',
                        Sponsor::TIER_MELODY => 'warning',
                        Sponsor::TIER_HARMONY => 'gray',
                        default => 'gray',
                    })
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('tier', $direction)),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === Sponsor::TYPE_CASH ? 'Cash' : 'In-Kind')
                    ->color(fn ($state) => $state === Sponsor::TYPE_CASH ? 'primary' : 'secondary'),

                TextColumn::make('sponsored_memberships')
                    ->label('Memberships')
                    ->suffix('/mo')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->date()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tier')
                    ->options(Sponsor::getTiers()),

                SelectFilter::make('type')
                    ->options([
                        Sponsor::TYPE_CASH => 'Cash Sponsorship',
                        Sponsor::TYPE_IN_KIND => 'In-Kind Partnership',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                TrashedFilter::make(),
            ])
            ->defaultSort('display_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
