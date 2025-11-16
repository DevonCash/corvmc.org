<?php

namespace App\Filament\Resources\Reservations;

use App\Filament\Resources\Reservations\Pages\CalendarReservations;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Filament\Resources\Reservations\Widgets\FreeHoursWidget;
use App\Filament\Resources\Reservations\Widgets\RecurringSeriesTableWidget;
use App\Models\RehearsalReservation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReservationResource extends Resource
{
    protected static ?string $model = RehearsalReservation::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-metronome';

    protected static ?string $navigationLabel = 'Practice Space';

    public static function form(Schema $schema): Schema
    {
        return ReservationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show count of current user's upcoming reservations
        return RehearsalReservation::where('reservable_type', User::class)
            ->where('reservable_id', Auth::id())
            ->where('status', '!=', 'cancelled')
            ->where('reserved_at', '>', now())
            ->count() ?: null;
    }

    public static function getWidgets(): array
    {
        return [
            RecurringSeriesTableWidget::class,
            FreeHoursWidget::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'calendar' => CalendarReservations::route('/calendar'),
            'index' => ListReservations::route('/'),
        ];
    }
}
