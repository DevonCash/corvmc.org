<?php

namespace CorvMC\Finance\Exceptions;

use CorvMC\Finance\Models\Order;
use Illuminate\Database\Eloquent\Model;

/**
 * Thrown when code attempts to modify a domain model (Purchasable)
 * that is bound to an active (non-terminal) Order.
 *
 * The only path forward is to cancel the Order first, then rebook.
 */
class PurchasableLockedException extends \RuntimeException
{
    public function __construct(
        public readonly Model $model,
        public readonly Order $order,
    ) {
        $modelClass = get_class($model);
        $modelId = $model->getKey();

        parent::__construct(
            "Cannot modify {$modelClass}#{$modelId}: it is locked by active Order#{$order->id} "
            . "(status: {$order->status}). Cancel the Order first, then rebook."
        );
    }
}
