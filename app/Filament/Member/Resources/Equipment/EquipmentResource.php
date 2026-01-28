<?php

namespace App\Filament\Member\Resources\Equipment;

use App\Filament\Member\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Member\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Member\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Member\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Member\Resources\Equipment\Schemas\EquipmentForm;
use App\Filament\Member\Resources\Equipment\Schemas\EquipmentInfolist;
use App\Filament\Member\Resources\Equipment\Tables\EquipmentTable;
use CorvMC\Equipment\Models\Equipment;
use App\Settings\EquipmentSettings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-device-speaker';

    protected static ?string $navigationLabel = 'Equipment';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EquipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentTable::configure($table);
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
            'index' => ListEquipment::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $equipmentSettings = app(EquipmentSettings::class);

        return $equipmentSettings->enable_equipment_features;
    }
}
