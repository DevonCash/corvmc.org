<?php

namespace CorvMC\Support\States;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;

class BaseState extends State implements HasDescription, HasColor, HasIcon, HasLabel, CallbackStateContract
{
    public function getDescription(): string
    {
        return static::$description ?? '';
    }

    public function getColor(): string
    {
        return static::$color ?? 'primary';
    }

    public function getIcon(): string
    {
        return static::$icon ?? '';
    }

    public function getLabel(): string
    {
        return static::$label ?? '';
    }

    public function isFinal(): bool
    {
        return empty($this->transitionableStates());
    }

    public function entering(): void {}
    public function exiting(): void {}
    public function exited(): void {}
    public function entered(): void {}
}
