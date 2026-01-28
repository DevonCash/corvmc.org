<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Models\Equipment;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkReturnedToOwner
{
    use AsAction;

    /**
     * Mark equipment as returned to original owner.
     */
    public function handle(Equipment $equipment): void
    {
        $equipment->update([
            'ownership_status' => 'returned_to_owner',
            'status' => 'retired',
        ]);
    }
}
