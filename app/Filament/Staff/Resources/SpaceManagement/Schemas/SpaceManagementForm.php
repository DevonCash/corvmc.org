<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Schemas;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
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
