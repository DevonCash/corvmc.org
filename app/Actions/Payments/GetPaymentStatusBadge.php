<?php

namespace App\Actions\Payments;

use App\Models\Reservation;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPaymentStatusBadge
{
    use AsAction;

    /**
     * Get payment status badge information for UI display.
     */
    public function handle(Reservation $reservation): array
    {
        return match ($reservation->payment_status) {
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'comped' => ['label' => 'Comped', 'color' => 'info'],
            'refunded' => ['label' => 'Refunded', 'color' => 'danger'],
            'unpaid' => ['label' => 'Unpaid', 'color' => 'danger'],
            default => ['label' => 'Unknown', 'color' => 'gray'],
        };
    }
}
