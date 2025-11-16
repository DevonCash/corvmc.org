<?php

namespace App\Filament\Resources\Equipment\EquipmentDamageReports;

use App\Filament\Resources\Equipment\EquipmentDamageReports\Pages\CreateEquipmentDamageReport;
use App\Filament\Resources\Equipment\EquipmentDamageReports\Pages\EditEquipmentDamageReport;
use App\Filament\Resources\Equipment\EquipmentDamageReports\Pages\ListEquipmentDamageReports;
use App\Filament\Resources\Equipment\EquipmentDamageReports\Schemas\EquipmentDamageReportForm;
use App\Filament\Resources\Equipment\EquipmentDamageReports\Tables\EquipmentDamageReportsTable;
use App\Models\EquipmentDamageReport;
use App\Settings\EquipmentSettings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EquipmentDamageReportResource extends Resource
{
    protected static ?string $model = EquipmentDamageReport::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Damage Reports';

    protected static ?string $modelLabel = 'Damage Report';

    protected static ?string $pluralModelLabel = 'Damage Reports';

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return config('filament-icons.app-equipment-damage-report');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $equipmentSettings = app(EquipmentSettings::class);

        return $equipmentSettings->enable_equipment_features && $equipmentSettings->enable_rental_features;
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentDamageReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentDamageReportsTable::configure($table);
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
            'index' => ListEquipmentDamageReports::route('/'),
            'create' => CreateEquipmentDamageReport::route('/create'),
            'edit' => EditEquipmentDamageReport::route('/{record}/edit'),
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
