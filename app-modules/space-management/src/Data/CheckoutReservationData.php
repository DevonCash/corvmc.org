<?php

namespace CorvMC\SpaceManagement\Data;

use CorvMC\SpaceManagement\Models\Reservation;
use Spatie\LaravelData\Data;

class CheckoutReservationData extends Data
{
    public function __construct(
        public Reservation $reservation,
        public bool $coverFees = false,
        public ?string $successUrl = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
    ) {}

    public static function rules(): array
    {
        return [
            'reservation' => ['required', 'exists:reservations,id'],
            'coverFees' => ['boolean'],
            'successUrl' => ['nullable', 'url'],
            'cancelUrl' => ['nullable', 'url'],
            'metadata' => ['array'],
        ];
    }
}