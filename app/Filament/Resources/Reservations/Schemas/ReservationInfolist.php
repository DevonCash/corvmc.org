<?php

namespace App\Filament\Resources\Reservations\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('reservation_details')
                    ->view('filament.resources.reservations.infolist.reservation-details-member'),
            ]);
    }
}
