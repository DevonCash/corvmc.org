<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;

/**
 * @deprecated Use EquipmentService::getValueByAcquisitionType() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class GetValueByAcquisitionType
{
    /**
     * @deprecated Use EquipmentService::getValueByAcquisitionType() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->getValueByAcquisitionType(...$args);
    }
}
