<?php

namespace App\Filament\Staff\Resources\SpaceManagement;

use App\Filament\Staff\Resources\SpaceManagement\Pages\ListSpaceUsage;
use App\Filament\Staff\Resources\SpaceManagement\Schemas\SpaceManagementForm;
use App\Filament\Staff\Resources\SpaceManagement\Tables\SpaceManagementTable;
use CorvMC\Finance\Models\Charge;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        return parent::getEloquentQuery()
            ->with(['reservable'])
            ->afterQuery(function (Collection $models) {
                if ($models->isEmpty()) {
                    return $models;
                }

                // Bulk-load charges to avoid N+1 queries.
                // Standard ->with(['charge']) doesn't work because the base Reservation
                // morph class ('reservation') doesn't match the actual chargeable_type
                // ('rehearsal_reservation') stored on charges due to STI.
                $charges = Charge::whereIn('chargeable_id', $models->pluck('id'))
                    ->whereIn('chargeable_type', ['rehearsal_reservation', 'event_reservation'])
                    ->get()
                    ->keyBy('chargeable_id');

                // Pre-compute "first reservation" flags to avoid N+1 EXISTS queries
                // from isFirstReservationForUser() called in the responsibleUser column.
                $userReservableIds = $models
                    ->where('reservable_type', 'user')
                    ->pluck('reservable_id')
                    ->unique()
                    ->values();

                $usersWithMultipleRehearsals = collect();
                if ($userReservableIds->isNotEmpty()) {
                    $usersWithMultipleRehearsals = DB::table('reservations')
                        ->select('reservable_id')
                        ->where('reservable_type', 'user')
                        ->where('type', 'rehearsal_reservation')
                        ->whereIn('reservable_id', $userReservableIds)
                        ->groupBy('reservable_id')
                        ->havingRaw('COUNT(*) > 1')
                        ->pluck('reservable_id')
                        ->flip();
                }

                foreach ($models as $model) {
                    $model->setRelation('charge', $charges->get($model->id));

                    if ($model->reservable_type === 'user') {
                        $model->setIsFirstReservation(
                            ! $usersWithMultipleRehearsals->has($model->reservable_id)
                        );
                    }
                }

                return $models;
            });
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
            'view' => Pages\ViewSpaceUsage::route('/{record}'),
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
