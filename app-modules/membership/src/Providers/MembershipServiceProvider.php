<?php

namespace CorvMC\Membership\Providers;

use CorvMC\Membership\Services\BandService;
use CorvMC\Membership\Services\MemberProfileService;
use CorvMC\Membership\Services\StaffProfileService;
use CorvMC\Membership\Services\UserManagementService;
use CorvMC\Membership\Listeners\SendBandInvitationAcceptedNotification;
use CorvMC\Membership\Listeners\SendBandInvitationNotification;
use CorvMC\Support\Events\InvitationAccepted;
use CorvMC\Support\Events\InvitationCreated;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MemberProfileService::class);
        $this->app->singleton(BandService::class);
        $this->app->singleton(UserManagementService::class);
        $this->app->singleton(StaffProfileService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'membership');

        Blade::componentNamespace('CorvMC\\Membership\\View\\Components', 'membership');

        Event::listen(InvitationCreated::class, SendBandInvitationNotification::class);
        Event::listen(InvitationAccepted::class, SendBandInvitationAcceptedNotification::class);
    }
}
