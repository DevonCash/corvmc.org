<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class EquipmentSettings extends Settings
{
    public bool $enable_equipment_features;

    public bool $enable_rental_features;

    public static function group(): string
    {
        return 'equipment';
    }
}
