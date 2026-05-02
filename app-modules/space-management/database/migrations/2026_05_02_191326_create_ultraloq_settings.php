<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ultraloq.client_id', '');
        $this->migrator->add('ultraloq.client_secret', '');
        $this->migrator->add('ultraloq.access_token', '');
        $this->migrator->add('ultraloq.refresh_token', '');
        $this->migrator->add('ultraloq.token_expires_at', null);
        $this->migrator->add('ultraloq.device_id', '');
        $this->migrator->add('ultraloq.device_name', '');
    }

    public function down(): void
    {
        $this->migrator->delete('ultraloq.client_id');
        $this->migrator->delete('ultraloq.client_secret');
        $this->migrator->delete('ultraloq.access_token');
        $this->migrator->delete('ultraloq.refresh_token');
        $this->migrator->delete('ultraloq.token_expires_at');
        $this->migrator->delete('ultraloq.device_id');
        $this->migrator->delete('ultraloq.device_name');
    }
};
