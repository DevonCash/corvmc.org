<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;

/**
 * @deprecated Use EquipmentService::markReturnedToOwner() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class MarkReturnedToOwner
{
    /**
     * @deprecated Use EquipmentService::markReturnedToOwner() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->markReturnedToOwner(...$args);
    }
}
