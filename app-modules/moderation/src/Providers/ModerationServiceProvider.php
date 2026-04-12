<?php

namespace CorvMC\Moderation\Providers;

use CorvMC\Moderation\Services\ReportService;
use CorvMC\Moderation\Services\RevisionService;
use CorvMC\Moderation\Services\SpamPreventionService;
use CorvMC\Moderation\Services\TrustService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrustService::class);
        $this->app->singleton(RevisionService::class);
        $this->app->singleton(ReportService::class);
        $this->app->singleton(SpamPreventionService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'moderation');

        Blade::componentNamespace('CorvMC\\Moderation\\View\\Components', 'moderation');
    }
}
