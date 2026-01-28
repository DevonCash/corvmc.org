<?php

namespace App\Filament\Staff\Resources\LocalResources\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ResourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'resources';

    protected static ?string $title = 'Resources';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('website')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Full URL including https://'),
                    ]),

                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->maxLength(255),

                        TextInput::make('contact_email')
                            ->email()
                            ->maxLength(255),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('contact_phone')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('address')
                            ->maxLength(255),
                    ]),

                Grid::make(2)
                    ->schema([
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers display first'),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->helperText('Leave empty to save as draft'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('website')
                    ->url(fn ($record) => $record->website)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ($state->isPast() ? 'Published' : 'Scheduled') : 'Draft')
                    ->color(fn ($state) => $state ? ($state->isPast() ? 'success' : 'warning') : 'gray')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('published')
                    ->label('Status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'published' => $query->whereNotNull('published_at')->where('published_at', '<=', now()),
                            'draft' => $query->whereNull('published_at'),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}
