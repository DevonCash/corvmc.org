<?php

namespace App\Data;

use App\Enums\Visibility;
use Spatie\LaravelData\Data;

class ContactData extends Data
{
    public function __construct(
        public ?Visibility $visibility = Visibility::Private,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
    ) {}
}
