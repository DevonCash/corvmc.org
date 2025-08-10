<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('footer.links', [
            ['label' => 'About', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Contribute', 'url' => '/contribute'],
        ]);

        $this->migrator->add('footer.social_links', [
            ['icon' => 'tabler:brand-x', 'url' => '#'],
            ['icon' => 'tabler:brand-facebook', 'url' => '#'],
            ['icon' => 'tabler:brand-pinterest', 'url' => '#'],
            ['icon' => 'tabler:brand-instagram', 'url' => '#'],
        ]);
    }
};
