<?php

namespace App\Filament\Resources\Bands;

use App\Filament\Resources\Bands\Pages\EditBand;
use App\Filament\Resources\Bands\Pages\ListBands;
use App\Filament\Resources\Bands\Pages\ViewBand;
use App\Filament\Resources\Bands\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Bands\Schemas\BandForm;
use App\Filament\Resources\Bands\Tables\BandsTable;
use App\Models\Band;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BandResource extends Resource
{
    protected static ?string $model = Band::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-microphone-2';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Band Directory';

    protected static ?string $pluralModelLabel = 'Bands';

    protected static ?string $slug = 'bands';

    protected static ?string $modelLabel = 'Band';

    public static function getNavigationBadge(): ?string
    {
        $count = Band::whereHas('members', function ($query) {
            $query->where('user_id', User::me()->id)
                ->where('status', 'invited');
        })->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return BandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBands::route('/'),
            'view' => ViewBand::route('/{record}'),
            'edit' => EditBand::route('/{record}/edit'),
        ];
    }
}
