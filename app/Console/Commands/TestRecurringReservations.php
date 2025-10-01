<?php

namespace App\Console\Commands;

use App\Models\RecurringReservation;
use App\Models\User;
use App\Services\RecurringReservationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TestRecurringReservations extends Command
{
    protected $signature = 'test:recurring-reservations {--clean : Clean up test data before running}';

    protected $description = 'Test recurring reservations system end-to-end';

    public function handle(RecurringReservationService $service)
    {
        $this->info('🧪 Testing Recurring Reservations System');
        $this->line('==========================================');

        if ($this->option('clean')) {
            $this->cleanTestData();
        }

        // 1. Test creating weekly recurring reservation
        $this->info('');
        $this->info('📅 1. Creating weekly recurring reservation...');
        $user = User::first();

        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return 1;
        }

        // Give user sustaining member role for testing
        if (!$user->hasRole('sustaining member')) {
            $user->assignRole('sustaining member');
            $this->line('   ✓ Assigned sustaining member role to ' . $user->name);
        }

        try {
            $series = $service->createRecurringSeries(
                user: $user,
                recurrenceRule: 'FREQ=WEEKLY;BYDAY=TU',
                startDate: Carbon::now()->next('Tuesday'),
                startTime: '19:00',
                endTime: '21:00',
                endDate: Carbon::now()->addMonths(3),
                maxAdvanceDays: 90,
                notes: 'Test recurring series - Every Tuesday'
            );

            $this->line('   ✓ Created series #' . $series->id);
            $this->line('   ✓ Pattern: ' . $service->formatRuleForHumans($series->recurrence_rule));
            $this->line('   ✓ Time: ' . $series->start_time->format('g:i A') . ' - ' . $series->end_time->format('g:i A'));

            // Check generated instances
            $instances = $series->instances;
            $this->line('   ✓ Generated ' . $instances->count() . ' instances');

            if ($instances->count() > 0) {
                $this->line('   ✓ First instance: ' . $instances->first()->reserved_at->format('M d, Y g:i A'));
            }

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: ' . $e->getMessage());
            return 1;
        }

        // 2. Test extending series
        $this->info('');
        $this->info('📆 2. Testing series extension...');
        try {
            $originalEndDate = $series->series_end_date;
            $newEndDate = $series->series_end_date->addMonths(1);

            $service->extendSeries($series, $newEndDate);
            $series->refresh();

            $this->line('   ✓ Extended from ' . $originalEndDate->format('M d, Y') . ' to ' . $newEndDate->format('M d, Y'));
            $this->line('   ✓ Now has ' . $series->instances()->count() . ' instances');

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: ' . $e->getMessage());
        }

        // 3. Test skipping instance
        $this->info('');
        $this->info('⏭️  3. Testing skip instance...');
        try {
            $upcomingInstances = $service->getUpcomingInstances($series, 5);

            if ($upcomingInstances->count() > 0) {
                $instanceToSkip = $upcomingInstances->first();
                $skipDate = Carbon::parse($instanceToSkip->instance_date);

                $service->skipInstance($series, $skipDate, 'Test skip');

                $instanceToSkip->refresh();
                $this->line('   ✓ Skipped instance on ' . $skipDate->format('M d, Y'));
                $this->line('   ✓ Status: ' . $instanceToSkip->status);
                $this->line('   ✓ Reason: ' . $instanceToSkip->cancellation_reason);
            } else {
                $this->warn('   → No upcoming instances to skip');
            }

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: ' . $e->getMessage());
        }

        // 4. Test cancelling series
        $this->info('');
        $this->info('🚫 4. Testing cancel series...');
        try {
            $activeInstancesBefore = $series->activeInstances()->count();

            $service->cancelSeries($series, 'Test cancellation');
            $series->refresh();

            $this->line('   ✓ Series status: ' . $series->status);
            $this->line('   ✓ Had ' . $activeInstancesBefore . ' active instances before cancellation');
            $this->line('   ✓ Now has ' . $series->activeInstances()->count() . ' active instances');

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: ' . $e->getMessage());
        }

        // 5. Test RRULE building
        $this->info('');
        $this->info('🔧 5. Testing RRULE builder...');
        try {
            $rrule1 = $service->buildRRule([
                'frequency' => 'WEEKLY',
                'interval' => 2,
                'by_day' => ['MO', 'WE', 'FR'],
            ]);
            $this->line('   ✓ Built: ' . $rrule1);
            $this->line('   ✓ Human: ' . $service->formatRuleForHumans($rrule1));

            $rrule2 = $service->buildRRule([
                'frequency' => 'MONTHLY',
                'interval' => 1,
            ]);
            $this->line('   ✓ Built: ' . $rrule2);
            $this->line('   ✓ Human: ' . $service->formatRuleForHumans($rrule2));

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: ' . $e->getMessage());
        }

        $this->info('');
        $this->info('✅ All tests completed!');
        $this->info('');
        $this->line('To clean up test data, run:');
        $this->line('  php artisan test:recurring-reservations --clean');

        return 0;
    }

    protected function cleanTestData(): void
    {
        $this->warn('🧹 Cleaning up test data...');

        $count = RecurringReservation::where('notes', 'LIKE', '%Test%')->count();
        RecurringReservation::where('notes', 'LIKE', '%Test%')->delete();

        $this->line("   ✓ Deleted {$count} test recurring reservations");
    }
}
