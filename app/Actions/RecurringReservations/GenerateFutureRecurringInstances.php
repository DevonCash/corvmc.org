<?php

namespace App\Actions\RecurringReservations;

use App\Models\RecurringReservation;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateFutureRecurringInstances
{
    use AsAction;

    public string $commandSignature = 'recurring:generate-instances';
    public string $commandDescription = 'Generate future instances for all active recurring reservation series';

    /**
     * Scheduled job: Generate future instances for all active series.
     *
     * Can be run as a console command: php artisan recurring:generate-instances
     */
    public function handle(): void
    {
        $activeSeries = RecurringReservation::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('series_end_date')
                  ->orWhere('series_end_date', '>', now());
            })
            ->get();

        foreach ($activeSeries as $series) {
            GenerateRecurringInstances::run($series);
        }
    }

    /**
     * Console command implementation.
     */
    public function asCommand($command): void
    {
        $command->info('Generating future instances for active recurring series...');
        $this->handle();
        $command->info('✓ Future instances generated successfully');
    }
}
