<?php

namespace CorvMC\SpaceManagement\Concerns;

use CorvMC\SpaceManagement\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;

trait HasPaymentStatus
{
    protected function initializeHasPaymentStatus(): void
    {
        $this->mergeCasts([
            self::getPaymentStatusColumn() => PaymentStatus::class,
        ]);
    }

    protected static function getPaymentStatusColumn(): string
    {
        return 'payment_status';
    }

    #[Scope]
    public function scopeUnpaid($query)
    {
        return $query->where(self::getPaymentStatusColumn(), PaymentStatus::Unpaid->value);
    }
}
