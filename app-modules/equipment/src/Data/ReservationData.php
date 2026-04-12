<?php

namespace CorvMC\Equipment\Data;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Equipment\Models\Equipment;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class ReservationData extends Data
{
    public function __construct(
        public Equipment $equipment,
        public User $borrower,
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon $reserveFrom,
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon $dueDate,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'equipment' => ['required', 'exists:equipment,id'],
            'borrower' => ['required', 'exists:users,id'],
            'reserveFrom' => ['required', 'date', 'after_or_equal:today'],
            'dueDate' => ['required', 'date', 'after:reserveFrom'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}