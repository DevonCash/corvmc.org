<?php

namespace App\States\Equipment;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EquipmentState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Available::class)
            ->allowTransition(Available::class, Loaned::class)
            ->allowTransition(Available::class, Maintenance::class)
            ->allowTransition(Loaned::class, Available::class)
            ->allowTransition(Loaned::class, Maintenance::class)
            ->allowTransition(Maintenance::class, Available::class);
    }
    
    public function color(): string
    {
        return 'gray';
    }
    
    public function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }
    
    public function description(): string
    {
        return 'Unknown status';
    }
}