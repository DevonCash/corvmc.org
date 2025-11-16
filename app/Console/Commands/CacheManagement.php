<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class CacheManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:manage
                            {action : The action to perform (clear, warm, stats, clear-user, clear-date)}
                            {--user= : User ID to clear caches for (used with clear-user)}
                            {--date= : Date to clear caches for (Y-m-d format, used with clear-date)}
                            {--start-date= : Start date for date range clearing (Y-m-d format)}
                            {--end-date= : End date for date range clearing (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage application caches for optimal performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'clear' => $this->clearAllCaches(),
            'warm' => $this->warmUpCaches(),
            'stats' => $this->showCacheStats(),
            'clear-user' => $this->clearUserCaches(),
            'clear-date' => $this->clearDateCaches(),
            'clear-tags' => $this->clearTagCaches(),
            default => $this->error("Unknown action: {$action}")
        };

        return 0;
    }

    /**
     * Clear all application caches.
     */
    private function clearAllCaches(): void
    {
        $this->info('🧹 Clearing all caches...');

        if ($this->confirm('This will clear ALL application caches. Are you sure?')) {
            \App\Actions\Cache\ClearAllCaches::run();
            $this->info('✅ All caches cleared successfully!');
        } else {
            $this->info('❌ Cache clearing cancelled.');
        }
    }

    /**
     * Warm up commonly used caches.
     */
    private function warmUpCaches(): void
    {
        $this->info('🔥 Warming up caches...');

        $bar = $this->output->createProgressBar(3);
        $bar->start();

        \App\Actions\Cache\WarmUpCaches::run();
        $bar->advance();

        $this->info("\n✅ Caches warmed up successfully!");
        $bar->finish();
        $this->newLine();
    }

    /**
     * Show cache statistics.
     */
    private function showCacheStats(): void
    {
        $this->info('📊 Cache Statistics:');
        $this->newLine();

        $stats = \App\Actions\Cache\GetCacheStats::run();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Cache Driver', $stats['cache_driver']],
                ['Redis Connection', $stats['redis_connection'] ?? 'N/A'],
                ['Timestamp', $stats['timestamp']->format('Y-m-d H:i:s')],
            ]
        );
    }

    /**
     * Clear caches for a specific user.
     */
    private function clearUserCaches(): void
    {
        $userId = $this->option('user');

        if (! $userId) {
            $this->error('❌ User ID is required. Use --user=123');

            return;
        }

        $this->info("🧹 Clearing caches for user ID: {$userId}...");
        \App\Actions\Cache\ClearUserCaches::run((int) $userId);
        $this->info('✅ User caches cleared successfully!');
    }

    /**
     * Clear caches for a specific date or date range.
     */
    private function clearDateCaches(): void
    {
        $date = $this->option('date');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        if ($date) {
            $this->info("🧹 Clearing caches for date: {$date}...");
            \App\Actions\Cache\ClearReservationCaches::run($date);
            \App\Actions\Cache\ClearProductionCaches::run($date);
            $this->info('✅ Date caches cleared successfully!');
        } elseif ($startDate && $endDate) {
            $this->info("🧹 Clearing caches for date range: {$startDate} to {$endDate}...");
            \App\Actions\Cache\ClearDateRangeCaches::run(
                Carbon::createFromFormat('Y-m-d', $startDate),
                Carbon::createFromFormat('Y-m-d', $endDate)
            );
            $this->info('✅ Date range caches cleared successfully!');
        } else {
            $this->error('❌ Date is required. Use --date=2024-01-01 or --start-date=2024-01-01 --end-date=2024-01-31');
        }
    }

    /**
     * Clear tag-related caches.
     */
    private function clearTagCaches(): void
    {
        $this->info('🧹 Clearing member directory tag caches...');
        \App\Actions\Cache\ClearMemberDirectoryCaches::run();
        $this->info('✅ Tag caches cleared successfully!');
    }
}
