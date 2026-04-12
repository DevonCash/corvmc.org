<?php

namespace App\Filament\Staff\Resources\Users\RelationManagers;

use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $title = 'Events';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(1000),
                Forms\Components\DateTimePicker::make('start_time')
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time'),
                Forms\Components\TextInput::make('venue')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options(EventStatus::class)
                    ->default(EventStatus::Draft),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('venue')
                    ->searchable()
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('ticket_count')
                    ->label('Tickets')
                    ->placeholder('—')
                    ->getStateUsing(fn (Event $record) => $record->ticketed ? $record->tickets()->count() : null)
                    ->suffix(fn (Event $record) => $record->ticketed && $record->capacity ? "/{$record->capacity}" : ''),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(EventStatus::class),
                    
                Tables\Filters\TernaryFilter::make('ticketed')
                    ->label('Ticketed events')
                    ->placeholder('All events')
                    ->trueLabel('Ticketed only')
                    ->falseLabel('Free only'),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (Event $record) => $record->status === EventStatus::Draft),
            ])
            ->defaultSort('start_time', 'desc');
    }
}