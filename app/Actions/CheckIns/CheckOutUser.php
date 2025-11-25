<?php

namespace App\Actions\CheckIns;

use App\Models\CheckIn;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckOutUser
{
    use AsAction;

    /**
     * Check out a user by closing their open check-in
     *
     * @param  CheckIn  $checkIn  The check-in to close
     * @return CheckIn
     */
    public function handle(CheckIn $checkIn): CheckIn
    {
        if ($checkIn->isCheckedOut()) {
            throw new \InvalidArgumentException('This check-in has already been checked out.');
        }

        $checkIn->update([
            'checked_out_at' => now(),
        ]);

        return $checkIn->fresh();
    }
}
