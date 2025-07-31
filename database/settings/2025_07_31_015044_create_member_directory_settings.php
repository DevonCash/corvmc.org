<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('member_directory.available_flags', [
            'open_to_collaboration' => 'Open to Collaboration',
            'available_for_hire' => 'Available for Hire',
            'looking_for_band' => 'Looking for Band',
            'music_teacher' => 'Music Teacher',
        ]);
    }
};
