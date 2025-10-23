<?php

namespace App\Filament\Resources\SpaceManagement;

use App\Filament\Resources\SpaceManagement\Pages\CreateSpaceUsage;
use App\Filament\Resources\SpaceManagement\Pages\EditSpaceUsage;
use App\Filament\Resources\SpaceManagement\Pages\ListSpaceUsage;
use App\Filament\Resources\SpaceManagement\Pages\ViewSpaceUsage;
use App\Filament\Resources\SpaceManagement\Schemas\SpaceManagementForm;
use App\Filament\Resources\SpaceManagement\Tables\SpaceManagementTable;
use App\Models\Reservation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SpaceManagementResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-building';

    protected static ?string $navigationLabel = 'Space Management';

    protected static ?string $modelLabel = 'Space Usage';

    protected static ?string $pluralModelLabel = 'Space Usage';

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    /**
     * Only show to users who can manage practice space
     */
    public static function canAccess(): bool
    {
        return User::me()->can('manage practice space');
    }

    public static function form(Schema $schema): Schema
    {
        return SpaceManagementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpaceManagementTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of today's space usage (all reservation types)
        $total = Reservation::whereDate('reserved_at', today())
            ->where('status', '!=', 'cancelled')
            ->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaceUsage::route('/'),
            'create' => CreateSpaceUsage::route('/create'),
            'view' => ViewSpaceUsage::route('/{record}'),
            'edit' => EditSpaceUsage::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\SpaceUsageWidget::class,
            Widgets\SpaceStatsWidget::class,
        ];
    }
}
