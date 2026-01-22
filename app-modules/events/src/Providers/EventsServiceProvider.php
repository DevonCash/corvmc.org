<?php

namespace CorvMC\Events\Providers;

use App\Policies\EventPolicy;
use CorvMC\Events\Models\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class EventsServiceProvider extends ServiceProvider
{
	public function register(): void
	{
	}

	public function boot(): void
	{
		// Register the EventPolicy for the module's Event model
		Gate::policy(Event::class, EventPolicy::class);
	}
}
