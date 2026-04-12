<?php

namespace CorvMC\Finance\Data;

use CorvMC\Finance\Contracts\Chargeable;
use Spatie\LaravelData\Data;

class PaymentData extends Data
{
    public function __construct(
        public Chargeable $chargeable,
        public string $paymentMethod,
        public ?string $transactionId = null,
        public ?string $paymentIntentId = null,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'paymentMethod' => ['required', 'string', 'in:stripe,cash,card,venmo,paypal,zelle,check,manual,other'],
            'transactionId' => ['nullable', 'string', 'max:255'],
            'paymentIntentId' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}