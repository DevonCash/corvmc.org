<?php

namespace App\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    protected string $currency;

    public function __construct(string $currency = 'USD')
    {
        $this->currency = $currency;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::ofMinor($value, $this->currency);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->getMinorAmount()->toInt();
        }

        if (is_numeric($value)) {
            return Money::of($value, $this->currency)->getMinorAmount()->toInt();
        }

        if (is_string($value)) {
            return Money::of($value, $this->currency)->getMinorAmount()->toInt();
        }

        return null;
    }
}