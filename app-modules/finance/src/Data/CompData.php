<?php

namespace CorvMC\Finance\Data;

use CorvMC\Finance\Contracts\Chargeable;
use Spatie\LaravelData\Data;

class CompData extends Data
{
    public function __construct(
        public Chargeable $chargeable,
        public string $reason,
        public ?string $authorizedBy = null,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'authorizedBy' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}