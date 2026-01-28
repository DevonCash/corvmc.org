<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\SpaceManagement\Enums\ReservationStatus;
use App\Filament\Staff\Resources\SpaceManagement\Tables\SpaceManagementTable;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Google\Service\Compute\Resource\Reservations;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('reserved_at')
                    ->required(),
                Forms\Components\TextInput::make('duration_hours')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('free_hours_used')
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('status')
                    ->options(ReservationStatus::class)
                    ->default(ReservationStatus::Scheduled),
            ]);
    }

    public function table(Table $table): Table
    {
        return SpaceManagementTable::configure($table)->groups([])->defaultGroup(null);
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('reserved_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_hours')
                    ->numeric()
                    ->suffix(' hrs'),
                Tables\Columns\TextColumn::make('free_hours_used')
                    ->numeric()
                    ->suffix(' hrs'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ReservationStatus::class),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
