<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FooterSettings extends Settings
{
    public array $links;
    public array $social_links;
    
    public static function group(): string
    {
        return 'footer';
    }

    public function getLinks(): array
    {
        return $this->links ?? [
            ['label' => 'About', 'url' => '/about'],
            ['label' => 'Contact', 'url' => '/contact'],
            ['label' => 'Contribute', 'url' => '/contribute'],
        ];
    }

    public function getSocialLinks(): array
    {
        return $this->social_links ?? [
            ['icon' => 'tabler:brand-x', 'url' => '#'],
            ['icon' => 'tabler:brand-facebook', 'url' => '#'],
            ['icon' => 'tabler:brand-pinterest', 'url' => '#'],
            ['icon' => 'tabler:brand-instagram', 'url' => '#'],
        ];
    }
}