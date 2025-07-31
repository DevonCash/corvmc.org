<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ContactData extends Data
{
    public function __construct(
        public ?string $visibility = 'private',
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
    ) {}
}
