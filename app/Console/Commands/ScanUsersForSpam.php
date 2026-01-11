<?php

namespace App\Console\Commands;

use App\Actions\SpamPrevention\ScanUsersForSpam as ScanUsersForSpamAction;
use Illuminate\Console\Command;

class ScanUsersForSpam extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spam:scan
                            {--dry-run : Preview results without deleting}
                            {--remove : Delete identified spam users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan users for spam email addresses using StopForumSpam database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Determine mode
        $dryRun = $this->option('dry-run') || ! $this->option('remove');
        $removeSpam = $this->option('remove');

        if (! $dryRun && $removeSpam) {
            if (! $this->confirm('WARNING: This will PERMANENTLY DELETE users identified as spam. Continue?', false)) {
                $this->info('Scan cancelled.');

                return self::SUCCESS;
            }
        }

        $mode = $dryRun ? 'Dry Run' : 'Scan & Remove';
        $this->info("Starting spam scan ({$mode})...");
        $this->newLine();

        // Run the scan with progress bar
        $results = $this->withProgressBar(
            range(1, 1), // Single step since action handles all users internally
            function () use ($dryRun, $removeSpam) {
                return ScanUsersForSpamAction::run(
                    dryRun: $dryRun,
                    removeSpam: $removeSpam
                );
            }
        );

        $this->newLine(2);

        // Extract results from array
        $totalScanned = $results['total_scanned'];
        $spamFound = $results['spam_found'];
        $deleted = $results['deleted'];
        $errors = $results['errors'];
        $spamPercentage = $results['spam_percentage'];
        $spamUsers = $results['results'];

        // Display results
        if ($spamFound > 0) {
            $this->info('Spam Users Found:');
            $this->newLine();

            $tableData = $spamUsers->map(function ($user) {
                return [
                    'ID' => $user['id'],
                    'Name' => $user['name'],
                    'Email' => $user['email'],
                    'Registered' => $user['created_at']->format('Y-m-d H:i'),
                    'Frequency' => $user['frequency'],
                    'Last Seen' => $user['last_seen']?->format('Y-m-d') ?? 'N/A',
                    'Status' => $user['deleted'] ? '<fg=green>Deleted</>' : '<fg=yellow>Found</>',
                ];
            })->toArray();

            $this->table(
                ['ID', 'Name', 'Email', 'Registered', 'Frequency', 'Last Seen', 'Status'],
                $tableData
            );
        } else {
            $this->info('✓ No spam accounts found!');
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Total scanned: {$totalScanned}");
        $this->line("  Spam found: {$spamFound} ({$spamPercentage}%)");

        if ($errors > 0) {
            $this->warn("  Errors: {$errors}");
        }

        if (! $dryRun) {
            $this->line("  Deleted: {$deleted}");
        } else {
            $this->comment('  Mode: Dry run (no users deleted)');
        }

        $this->newLine();

        if ($dryRun && $spamFound > 0) {
            $this->comment('To delete these spam users, run: php artisan spam:scan --remove');
        }

        return self::SUCCESS;
    }

    /**
     * Execute callback with progress bar
     */
    private function withProgressBar(array $items, callable $callback): mixed
    {
        $bar = $this->output->createProgressBar(count($items));
        $bar->start();

        $result = null;
        foreach ($items as $item) {
            $result = $callback($item);
            $bar->advance();
        }

        $bar->finish();

        return $result;
    }
}
