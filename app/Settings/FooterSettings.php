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
        $links = $this->social_links ?? [
            ['icon' => 'tabler-brand-x', 'url' => '#'],
            ['icon' => 'tabler-brand-facebook', 'url' => '#'],
            ['icon' => 'tabler-brand-pinterest', 'url' => '#'],
            ['icon' => 'tabler-brand-instagram', 'url' => '#'],
        ];

        // Convert legacy tabler: format to tabler- format
        return array_map(function ($link) {
            if (isset($link['icon'])) {
                $link['icon'] = str_replace('tabler:', 'tabler-', $link['icon']);
            }

            return $link;
        }, $links);
    }
}
