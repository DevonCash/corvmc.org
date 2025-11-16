<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Story
{
    public array $addresses;

    public function __construct(string|array $addresses)
    {
        $this->addresses = is_array($addresses) ? $addresses : [$addresses];
    }

    public function getAddresses(): array
    {
        return $this->addresses;
    }
}
