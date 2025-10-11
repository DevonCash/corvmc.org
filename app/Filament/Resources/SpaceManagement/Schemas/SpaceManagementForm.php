<?php

namespace App\Filament\Resources\SpaceManagement\Schemas;

use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use Filament\Schemas\Schema;

class SpaceManagementForm
{
    public static function configure(Schema $schema): Schema
    {
        // Use the same form as the Reservation resource
        // It already has admin controls for selecting users
        return ReservationForm::configure($schema);
    }
}
