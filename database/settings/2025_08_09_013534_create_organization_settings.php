<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('organization.name', 'Corvallis Music Collective');
        $this->migrator->add('organization.ein', '123456789'); // Replace with actual EIN
        $this->migrator->add('organization.description', 'Supporting local musicians with affordable practice space, events, and community connections.');
        $this->migrator->add('organization.address', 'Corvallis, Oregon');
        $this->migrator->add('organization.phone', '');
        $this->migrator->add('organization.email', 'info@corvallismusiccollective.org');
    }
};
