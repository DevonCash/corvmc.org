<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BandsRelationManager extends RelationManager
{
    protected static string $relationship = 'bandProfiles';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('role')
                    ->maxLength(100),
                Forms\Components\TextInput::make('position')
                    ->maxLength(100),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'invited' => 'Invited',
                        'inactive' => 'Inactive',
                    ])
                    ->default('active'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.role')
                    ->label('Role')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.position')
                    ->label('Position')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pivot.status')
                    ->badge()
                    ->label('Status')
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pivot.invited_at')
                    ->label('Invited At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pivot.status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'invited' => 'Invited',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->schema(fn (Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('role')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('position')
                            ->maxLength(100),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'invited' => 'Invited',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active'),
                    ])
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                Actions\EditAction::make()
                    ->schema([
                        Forms\Components\TextInput::make('role')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('position')
                            ->maxLength(100),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'invited' => 'Invited',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active'),
                    ]),
                Actions\DetachAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
