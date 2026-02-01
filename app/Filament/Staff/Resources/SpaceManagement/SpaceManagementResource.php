<?php

namespace App\Filament\Staff\Resources\SpaceManagement;

use App\Filament\Staff\Resources\SpaceManagement\Pages\ListSpaceUsage;
use App\Filament\Staff\Resources\SpaceManagement\Schemas\SpaceManagementForm;
use App\Filament\Staff\Resources\SpaceManagement\Tables\SpaceManagementTable;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
     * Only show to users who can manage rehearsal reservations
     */
    public static function canAccess(): bool
    {
        return User::me()?->can('manage', RehearsalReservation::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SpaceManagementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpaceManagementTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        // Note: Don't eager load 'charge' here - polymorphic eager loading from base
        // Reservation model uses wrong morph class. Lazy loading works correctly
        // since STI resolves the correct subclass before accessing the relationship.
        return parent::getEloquentQuery()->with(['reservable']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of today's reservations that haven't ended yet
        $total = Reservation::whereDate('reserved_at', today())
            ->where('reserved_until', '>', now())
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
