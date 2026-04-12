<?php

namespace CorvMC\SpaceManagement\Data;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateReservationData extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon|Optional $startTime = new Optional(),
        
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon|Optional $endTime = new Optional(),
        
        public string|Optional $notes = new Optional(),
        
        public ReservationStatus|Optional $status = new Optional(),
        
        public bool $skipConflictCheck = false,
    ) {}

    public static function rules(): array
    {
        return [
            'startTime' => ['sometimes', 'date', 'after:now'],
            'endTime' => ['sometimes', 'date', 'after:startTime'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string'],
            'skipConflictCheck' => ['boolean'],
        ];
    }
}