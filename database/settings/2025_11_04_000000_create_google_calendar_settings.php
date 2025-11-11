<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('google_calendar.enable_google_calendar_sync', false);
        $this->migrator->add('google_calendar.google_calendar_id', null);
    }
};
