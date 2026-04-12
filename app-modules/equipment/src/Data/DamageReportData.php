<?php

namespace CorvMC\Equipment\Data;

use App\Models\User;
use CorvMC\Equipment\Models\Equipment;
use Spatie\LaravelData\Data;

class DamageReportData extends Data
{
    public function __construct(
        public Equipment $equipment,
        public User $reporter,
        public string $description,
        public string $severity = 'minor',
        public ?array $photos = null,
    ) {}

    public static function rules(): array
    {
        return [
            'equipment' => ['required', 'exists:equipment,id'],
            'reporter' => ['required', 'exists:users,id'],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'severity' => ['required', 'in:minor,moderate,severe'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'image', 'max:5120'], // 5MB max per image
        ];
    }

    public function isSevere(): bool
    {
        return $this->severity === 'severe';
    }
}