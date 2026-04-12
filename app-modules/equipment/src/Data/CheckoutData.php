<?php

namespace CorvMC\Equipment\Data;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Equipment\Models\Equipment;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class CheckoutData extends Data
{
    public function __construct(
        public Equipment $equipment,
        public User $borrower,
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon $dueDate,
        public string $conditionOut = 'good',
        #[Min(0)]
        public float $securityDeposit = 0,
        #[Min(0)]
        public float $rentalFee = 0,
        public ?string $notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'equipment' => ['required', 'exists:equipment,id'],
            'borrower' => ['required', 'exists:users,id'],
            'dueDate' => ['required', 'date', 'after:now'],
            'conditionOut' => ['required', 'in:excellent,good,fair,poor'],
            'securityDeposit' => ['nullable', 'numeric', 'min:0'],
            'rentalFee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}