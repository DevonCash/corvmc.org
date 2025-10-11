<?php

namespace App\Livewire\Synthesizers;

use Brick\Money\Money;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class MoneySynthesizer extends Synth
{
    public static string $key = 'money';

    public static function match($target): bool
    {
        return $target instanceof Money;
    }

    public function dehydrate(Money $target): array
    {
        return [
            [
                'amount' => $target->getAmount()->__toString(),
                'currency' => $target->getCurrency()->getCurrencyCode(),
            ],
            []
        ];
    }

    public function hydrate($value): Money
    {
        return Money::of($value['amount'], $value['currency']);
    }

    public function get(&$target, $key)
    {
        return $target->$key;
    }

    public function set(&$target, $key, $value)
    {
        $target->$key = $value;
    }
}
