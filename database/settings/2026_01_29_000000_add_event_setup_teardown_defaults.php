<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('reservation.default_event_setup_minutes', 120);
        $this->migrator->add('reservation.default_event_teardown_minutes', 60);
    }
};
