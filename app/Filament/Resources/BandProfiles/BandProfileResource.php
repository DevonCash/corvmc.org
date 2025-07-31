<?php

namespace App\Filament\Resources\BandProfiles;

use App\Filament\Resources\BandProfiles\Pages\CreateBandProfile;
use App\Filament\Resources\BandProfiles\Pages\EditBandProfile;
use App\Filament\Resources\BandProfiles\Pages\ListBandProfiles;
use App\Filament\Resources\BandProfiles\Schemas\BandProfileForm;
use App\Filament\Resources\BandProfiles\Tables\BandProfilesTable;
use App\Models\BandProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BandProfileResource extends Resource
{
    protected static ?string $model = BandProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return BandProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BandProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBandProfiles::route('/'),
            'create' => CreateBandProfile::route('/create'),
            'edit' => EditBandProfile::route('/{record}/edit'),
        ];
    }
}
