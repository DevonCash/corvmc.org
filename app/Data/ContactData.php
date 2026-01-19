<?php

namespace App\Data;

use App\Data\Casts\PhoneNumberCast;
use App\Enums\Visibility;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class ContactData extends Data
{
    public function __construct(
        public ?Visibility $visibility = Visibility::Private,
        public ?string $email = null,
        #[WithCast(PhoneNumberCast::class, 'US')]
        public ?string $phone = null,
        public bool $sms_ok = false,
        public ?string $address = null,
    ) {}
}
