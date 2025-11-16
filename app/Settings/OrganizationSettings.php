<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OrganizationSettings extends Settings
{
    public string $name;

    public string $ein;

    public ?string $description;

    public ?string $address;

    public ?string $phone;

    public string $email;

    public static function group(): string
    {
        return 'organization';
    }

    public function getFormattedEin(): string
    {
        if (strlen($this->ein) === 9) {
            return substr($this->ein, 0, 2).'-'.substr($this->ein, 2);
        }

        return $this->ein;
    }

    public function getNonprofitStatus(): string
    {
        return '501(c)(3) Nonprofit Organization';
    }

    public function getFullNonprofitDescription(): string
    {
        return $this->getNonprofitStatus().' • EIN: '.$this->getFormattedEin();
    }
}
