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
        return method_exists($this, 'description') ? $this->description() : (static::$description ?? '');
    }

    public function getColor(): string
    {
        return method_exists($this, 'color') ? $this->color() : (static::$color ?? 'primary');
    }

    public function getIcon(): string
    {
        return method_exists($this, 'icon') ? $this->icon() : (static::$icon ?? '');
    }

    public function getLabel(): string
    {
        return method_exists($this, 'label') ? $this->label() : (static::$label ?? '');
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
