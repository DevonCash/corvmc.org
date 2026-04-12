<?php

namespace CorvMC\Equipment\Data;

use CorvMC\Equipment\Models\EquipmentLoan;
use Spatie\LaravelData\Data;

class ReturnData extends Data
{
    public function __construct(
        public EquipmentLoan $loan,
        public string $conditionIn = 'good',
        public ?string $returnNotes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'loan' => ['required', 'exists:equipment_loans,id'],
            'conditionIn' => ['required', 'in:excellent,good,fair,poor'],
            'returnNotes' => ['nullable', 'string', 'max:500'],
        ];
    }
}