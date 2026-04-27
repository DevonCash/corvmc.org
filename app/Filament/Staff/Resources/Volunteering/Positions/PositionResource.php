<?php

namespace App\Filament\Staff\Resources\Volunteering\Positions;

use App\Filament\Staff\Resources\Volunteering\Positions\Pages\CreatePosition;
use App\Filament\Staff\Resources\Volunteering\Positions\Pages\EditPosition;
use App\Filament\Staff\Resources\Volunteering\Positions\Pages\ListPositions;
use App\Filament\Staff\Resources\Volunteering\Positions\RelationManagers\HourLogsRelationManager;
use App\Filament\Staff\Resources\Volunteering\Positions\RelationManagers\ShiftsRelationManager;
use App\Filament\Staff\Resources\Volunteering\Positions\Schemas\PositionForm;
use App\Filament\Staff\Resources\Volunteering\Positions\Tables\PositionsTable;
use CorvMC\Volunteering\Models\Position;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-users-group';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Volunteering';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return PositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PositionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ShiftsRelationManager::class,
            HourLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPositions::route('/'),
            'create' => CreatePosition::route('/create'),
            'edit' => EditPosition::route('/{record}/edit'),
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
