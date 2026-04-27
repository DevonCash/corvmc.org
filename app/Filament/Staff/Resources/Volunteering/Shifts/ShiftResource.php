<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts;

use App\Filament\Staff\Resources\Volunteering\Shifts\Pages\CreateShift;
use App\Filament\Staff\Resources\Volunteering\Shifts\Pages\EditShift;
use App\Filament\Staff\Resources\Volunteering\Shifts\Pages\ListShifts;
use App\Filament\Staff\Resources\Volunteering\Shifts\RelationManagers\HourLogsRelationManager;
use App\Filament\Staff\Resources\Volunteering\Shifts\Schemas\ShiftForm;
use App\Filament\Staff\Resources\Volunteering\Shifts\Tables\ShiftsTable;
use CorvMC\Volunteering\Models\Shift;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-clock';

    protected static ?string $recordTitleAttribute = 'position.title';

    protected static string|\UnitEnum|null $navigationGroup = 'Volunteering';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            HourLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }
}
