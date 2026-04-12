<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;

/**
 * @deprecated Use EquipmentService::markOverdue() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class MarkOverdue
{
    /**
     * @deprecated Use EquipmentService::markOverdue() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->markOverdue(...$args);
    }
}
