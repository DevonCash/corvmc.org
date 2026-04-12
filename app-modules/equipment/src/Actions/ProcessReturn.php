<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EquipmentService::processReturn() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class ProcessReturn
{
    use AsAction;

    /**
     * @deprecated Use EquipmentService::processReturn() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->processReturn(...$args);
    }
}
