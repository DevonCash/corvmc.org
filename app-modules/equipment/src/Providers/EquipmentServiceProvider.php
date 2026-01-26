<?php

namespace CorvMC\Equipment\Providers;

use CorvMC\Equipment\Models\Equipment;
use CorvMC\Equipment\Policies\EquipmentPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class EquipmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Gate::policy(Equipment::class, EquipmentPolicy::class);

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'equipment');

        Blade::componentNamespace('CorvMC\\Equipment\\View\\Components', 'equipment');
    }
}
