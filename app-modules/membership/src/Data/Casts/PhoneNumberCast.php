<?php

namespace CorvMC\Membership\Data\Casts;

use Propaganistas\LaravelPhone\PhoneNumber;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class PhoneNumberCast implements Cast
{
    public function __construct(
        protected ?string $country = 'US'
    ) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $phone = new PhoneNumber($value, $this->country);

            return $phone->formatE164();
        } catch (\Exception $e) {
            // If parsing fails, return the original value
            return $value;
        }
    }
}
