<?php

namespace App\Filament\Staff\Resources\SitePages;

use App\Filament\Staff\Resources\SitePages\Pages\EditSitePage;
use App\Filament\Staff\Resources\SitePages\Pages\ListSitePages;
use App\Filament\Staff\Resources\SitePages\Schemas\SitePageForm;
use App\Models\SitePage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SitePageResource extends Resource
{
    protected static ?string $model = SitePage::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-file-text';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Site Page';

    protected static ?string $pluralModelLabel = 'Site Pages';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SitePageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('updatedBy.name')
                    ->label('Last edited by')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since(),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSitePages::route('/'),
            'edit' => EditSitePage::route('/{record}/edit'),
        ];
    }
}
