<?php

namespace App\Filament\Resources\Equipment\EquipmentLoans;

use App\Filament\Resources\Equipment\EquipmentLoans\Pages\CreateEquipmentLoan;
use App\Filament\Resources\Equipment\EquipmentLoans\Pages\EditEquipmentLoan;
use App\Filament\Resources\Equipment\EquipmentLoans\Pages\ListEquipmentLoans;
use App\Filament\Resources\Equipment\EquipmentLoans\Schemas\EquipmentLoanForm;
use App\Filament\Resources\Equipment\EquipmentLoans\Tables\EquipmentLoansTable;
use App\Models\EquipmentLoan;
use App\Models\User;
use App\Settings\EquipmentSettings;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class EquipmentLoanResource extends Resource
{
    protected static ?string $model = EquipmentLoan::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-clipboard-list';

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $modelLabel = 'Equipment Loan';

    protected static ?string $pluralModelLabel = 'Equipment Loans';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return config('filament-icons.app-equipment-loan');
    }

    public static function shouldRegisterNavigation(): bool
    {
        $equipmentSettings = app(EquipmentSettings::class);

        return $equipmentSettings->enable_equipment_features && $equipmentSettings->enable_rental_features;
    }

    public static function canCreate(): bool
    {
        return User::me()?->can('create equipment loans') ?? false;
    }

    public static function canEdit($record): bool
    {
        return User::me()?->can('edit equipment loans') ?? false;
    }

    public static function canDelete($record): bool
    {
        return User::me()?->can('delete equipment loans') ?? false;
    }

    public static function canView($record): bool
    {
        return User::me()?->can('view equipment loans') ?? true;
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentLoanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentLoansTable::configure($table);
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
            'index' => ListEquipmentLoans::route('/'),
            'create' => CreateEquipmentLoan::route('/create'),
            'edit' => EditEquipmentLoan::route('/{record}'),
        ];
    }
}
