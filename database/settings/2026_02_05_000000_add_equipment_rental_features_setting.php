<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('equipment.enable_rental_features')) {
            $this->migrator->add('equipment.enable_rental_features', false);
        }
    }
};
