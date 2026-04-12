<?php

namespace CorvMC\Sponsorship\Providers;

use CorvMC\Sponsorship\Services\SponsorshipService;
use Illuminate\Support\ServiceProvider;

class SponsorshipServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SponsorshipService::class);
	}
	
	public function boot(): void
	{
	}
}
