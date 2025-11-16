<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class EquipmentData extends Data
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $brand,
        public ?string $model,
        public ?string $serial_number,
        public ?string $description,
        public string $condition,
        public string $acquisition_type,
        public ?int $provider_id,
        public ?ContactData $provider_contact,
        public string $acquisition_date,
        public ?string $return_due_date = null,
        public ?string $acquisition_notes = null,
        public string $ownership_status = 'cmc_owned',
        public string $status = 'available',
        public ?string $estimated_value = null,
        public ?string $location = null,
        public ?string $notes = null,
    ) {}
}
