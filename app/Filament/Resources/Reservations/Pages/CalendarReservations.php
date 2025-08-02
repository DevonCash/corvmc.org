<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Widgets\PracticeSpaceCalendar;
use Filament\Resources\Pages\Page;

class CalendarReservations extends Page
{
    protected static string $resource = ReservationResource::class;

    protected string $view = 'filament.resources.reservations.pages.calendar-reservations';

    protected static ?string $title = 'Reservation Calendar';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?int $navigationSort = 1;

    protected function getHeaderWidgets(): array
    {
        return [
            PracticeSpaceCalendar::class,
        ];
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check();
    }
}