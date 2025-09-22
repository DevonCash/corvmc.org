<?php

namespace App\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    protected string $defaultCurrency;
    protected ?string $currencyColumn;

    public function __construct(string $defaultCurrency = 'USD', ?string $currencyColumn = null)
    {
        $this->defaultCurrency = $defaultCurrency;
        
        // If currencyColumn is 3 uppercase letters, treat it as a fixed currency code
        if ($currencyColumn && preg_match('/^[A-Z]{3}$/', $currencyColumn)) {
            $this->defaultCurrency = $currencyColumn;
            $this->currencyColumn = null;
        } else {
            $this->currencyColumn = $currencyColumn;
        }
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $this->resolveCurrency($attributes);
        return Money::ofMinor($value, $currency);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            // If setting a Money object and we have a currency column, update it
            if ($this->currencyColumn && isset($attributes[$this->currencyColumn])) {
                $model->setAttribute($this->currencyColumn, $value->getCurrency()->getCurrencyCode());
            }
            return $value->getMinorAmount()->toInt();
        }

        if (is_numeric($value)) {
            $currency = $this->resolveCurrency($attributes);
            return Money::of($value, $currency)->getMinorAmount()->toInt();
        }

        if (is_string($value)) {
            $currency = $this->resolveCurrency($attributes);
            return Money::of($value, $currency)->getMinorAmount()->toInt();
        }

        return null;
    }

    /**
     * Resolve the currency to use for this cast.
     */
    protected function resolveCurrency(array $attributes): string
    {
        // If we have a currency column specified, use its value
        if ($this->currencyColumn && isset($attributes[$this->currencyColumn])) {
            return $attributes[$this->currencyColumn];
        }

        // Fall back to default currency
        return $this->defaultCurrency;
    }
}