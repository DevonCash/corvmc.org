<?php

namespace App\Filament\Staff\Resources\RecurringReservations;

use App\Filament\Staff\Resources\RecurringReservations\Pages\CreateRecurringReservation;
use App\Filament\Staff\Resources\RecurringReservations\Pages\EditRecurringReservation;
use App\Filament\Staff\Resources\RecurringReservations\Pages\ListRecurringReservations;
use App\Filament\Staff\Resources\RecurringReservations\Schemas\RecurringReservationForm;
use App\Filament\Staff\Resources\RecurringReservations\Tables\RecurringReservationsTable;
use CorvMC\Support\Models\RecurringSeries;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringReservationResource extends Resource
{
    protected static ?string $model = RecurringSeries::class;

    protected static ?string $modelLabel = 'Recurring Rehearsal';

    protected static ?string $pluralModelLabel = 'Recurring Rehearsals';

    protected static \BackedEnum|string|null $navigationIcon = 'tabler-calendar-repeat';

    protected static string|\UnitEnum|null $navigationGroup = 'Reservations';

    protected static ?string $navigationLabel = 'Recurring Rehearsals';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'recurring-reservations';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('admin') || $user?->hasRole('practice space manager');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('recurable_type', 'rehearsal_reservation');
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
