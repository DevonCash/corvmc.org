<?php

namespace CorvMC\Equipment\Actions;

use CorvMC\Equipment\Services\EquipmentService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use EquipmentService::markOverdue() instead
 * This action is maintained for backward compatibility only.
 * New code should use the EquipmentService directly.
 */
class MarkOverdue
{
    use AsAction;

    /**
     * @deprecated Use EquipmentService::markOverdue() instead
     */
    public function handle(...$args)
    {
        return app(EquipmentService::class)->markOverdue(...$args);
    }
}
