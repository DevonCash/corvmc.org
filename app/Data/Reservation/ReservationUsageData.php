<?php

namespace App\Data\Reservation;

use Spatie\LaravelData\Data;

class ReservationUsageData extends Data
{
    public function __construct(
        public string $month,
        public int $total_reservations,
        public float $total_hours,
        public float $free_hours_used,
        public float $total_cost,
        public int $allocated_free_hours,
    ) {}
}
