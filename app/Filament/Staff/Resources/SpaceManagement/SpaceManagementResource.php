<?php

namespace App\Filament\Staff\Resources\SpaceManagement;

use App\Filament\Staff\Resources\SpaceManagement\Pages\ListSpaceUsage;
use App\Filament\Staff\Resources\SpaceManagement\Schemas\SpaceManagementForm;
use App\Filament\Staff\Resources\SpaceManagement\Tables\SpaceManagementTable;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

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
