<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;

/**
 * @deprecated Use EquipmentService::getStatistics() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class GetStatistics
{
    /**
     * @deprecated Use EquipmentService::getStatistics() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->getStatistics(...$args);
    }
}
