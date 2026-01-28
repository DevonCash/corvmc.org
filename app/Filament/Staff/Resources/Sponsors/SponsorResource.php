<?php

namespace App\Filament\Staff\Resources\Sponsors;

use App\Filament\Staff\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Staff\Resources\Sponsors\Pages\EditSponsor;
use App\Filament\Staff\Resources\Sponsors\Pages\ListSponsors;
use App\Filament\Staff\Resources\Sponsors\RelationManagers\SponsoredMembersRelationManager;
use App\Filament\Staff\Resources\Sponsors\Schemas\SponsorForm;
use App\Filament\Staff\Resources\Sponsors\Tables\SponsorsTable;
use CorvMC\Sponsorship\Models\Sponsor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-heart-handshake';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return SponsorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SponsorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SponsoredMembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
