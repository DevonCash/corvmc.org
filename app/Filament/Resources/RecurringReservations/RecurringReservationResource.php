<?php

namespace App\Filament\Resources\RecurringReservations;

use App\Filament\Resources\RecurringReservations\Pages\CreateRecurringReservation;
use App\Filament\Resources\RecurringReservations\Pages\EditRecurringReservation;
use App\Filament\Resources\RecurringReservations\Pages\ListRecurringReservations;
use App\Filament\Resources\RecurringReservations\Schemas\RecurringReservationForm;
use App\Filament\Resources\RecurringReservations\Tables\RecurringReservationsTable;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\Support\Models\RecurringSeries;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringReservationResource extends Resource
{
    protected static ?string $model = RecurringSeries::class;

    protected static \BackedEnum|string|null $navigationIcon = 'tabler-clock-repeat';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    protected static ?string $navigationLabel = 'Recurring Reservations';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'recurring-reservations';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('recurable_type', Reservation::class);
    }

    public static function form(Schema $schema): Schema
    {
        return RecurringReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecurringReservationsTable::configure($table);
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
            'index' => ListRecurringReservations::route('/'),
            'create' => CreateRecurringReservation::route('/create'),
            'edit' => EditRecurringReservation::route('/{record}/edit'),
        ];
    }
}
