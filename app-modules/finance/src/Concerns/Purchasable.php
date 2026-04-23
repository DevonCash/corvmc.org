<?php

namespace CorvMC\Finance\Concerns;

/**
 * Trait for models that can be referenced by Finance Orders.
 *
 * Models using this trait declare which fields are safe to modify
 * while an active Order references them. The purchasable lock
 * allows changes to these fields and blocks everything else.
 */
trait Purchasable
{
    /**
     * Fields that can change while an active Order references this model.
     * Typically status fields and their associated timestamps/reasons.
     *
     * Override in your model to customize.
     */
    public function getLockableFields(): array
    {
        return ['status', 'updated_at'];
    }
}
