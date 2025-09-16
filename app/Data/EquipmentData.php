<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class EquipmentData extends Data
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $serial_number = null,
        public ?string $description = null,
        public string $condition = 'good',
        public string $acquisition_type = 'donated',
        public ?int $provider_id = null,
        public ?ContactData $provider_contact = null,
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