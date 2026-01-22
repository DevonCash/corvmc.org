<?php

namespace App\Console\Commands;

use App\Data\LocationData;
use App\Models\Band;
use CorvMC\Events\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportProductionsFromCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:productions
                            {file : Path to the CSV file}
                            {--dry-run : Show what would be imported without saving}
                            {--manager= : Default manager user ID for events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import events from a CSV file containing show data (legacy productions)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $dryRun = $this->option('dry-run');
        $defaultManagerId = $this->option('manager');

        if (! file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");

            return 1;
        }

        $this->info('🎵 Starting production import...');
        $this->line('================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be saved');
        }

        // Find default manager
        $defaultManager = null;
        if ($defaultManagerId) {
            $defaultManager = User::find($defaultManagerId);
            if (! $defaultManager) {
                $this->error("❌ Manager with ID {$defaultManagerId} not found");

                return 1;
            }
            $this->info("📝 Default manager: {$defaultManager->name}");
        }

        // Parse CSV
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) || $this->isSkippableRow($row)) {
                $skipped++;

                continue;
            }

            try {
                $productionData = $this->parseRow($headers, $row, $defaultManager);

                if ($productionData) {
                    $this->info("📅 {$productionData['date']} - {$productionData['title']}");

                    if ($productionData['local_bands']) {
                        $this->line('   🎸 Local: '.implode(', ', $productionData['local_bands']));
                    }
                    if ($productionData['touring_bands']) {
                        $this->line('   🚐 Touring: '.implode(', ', $productionData['touring_bands']));
                    }
                    if ($productionData['cover']) {
                        $this->line("   💰 Cover: {$productionData['cover']}");
                    }
                    if ($productionData['notes']) {
                        $this->line("   📝 Notes: {$productionData['notes']}");
                    }

                    if (! $dryRun) {
                        $this->createProduction($productionData);
                    }

                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors[] = 'Row '.($imported + $skipped + 1).': '.$e->getMessage();
                $this->error('❌ Error processing row: '.$e->getMessage());
                $skipped++;
            }
        }

        fclose($handle);

        // Summary
        $this->newLine();
        $this->info('📊 Import Summary:');
        $this->line("   ✅ Imported: {$imported}");
        $this->line("   ⏭️  Skipped: {$skipped}");

        if (! empty($errors)) {
            $this->line('   ❌ Errors: '.count($errors));
            foreach ($errors as $error) {
                $this->line("      • {$error}");
            }
        }

        if ($dryRun) {
            $this->warn('👆 This was a dry run - no data was actually imported');
        }

        return 0;
    }

    /**
     * Parse a CSV row into production data.
     */
    private function parseRow(array $headers, array $row, ?User $defaultManager): ?array
    {
        $data = array_combine($headers, $row);

        // Skip if no date or invalid date
        $dateStr = trim($data['Date'] ?? '');
        if (empty($dateStr) || ! $this->isValidDate($dateStr)) {
            return null;
        }

        // Parse date
        $date = Carbon::createFromFormat('m/d/Y', $dateStr);

        // Parse time
        $startTime = $this->parseTime($data['Start Time'] ?? '7:00 PM');
        $showStart = $date->copy()->setTime($startTime['hour'], $startTime['minute']);
        $showEnd = $showStart->copy()->addHours(4); // Default 4-hour show

        // Parse bands
        $touringBands = $this->parseBands($data['Touring Band (if applicable)'] ?? '');
        $localBands = $this->parseBands($data['Local Support'] ?? '');

        // Create title from bands
        $allBands = array_merge($touringBands, $localBands);
        $title = ! empty($allBands) ? implode(', ', array_slice($allBands, 0, 3)) : 'Show';
        if (count($allBands) > 3) {
            $title .= ' + more';
        }

        // Parse cover charge
        $coverStr = trim($data['Cover'] ?? '');
        $ticketPrice = $this->parseCoverCharge($coverStr);

        // Parse notes
        $notes = trim($data['Notes'] ?? '');

        // Determine manager (could be enhanced to parse from 'Show Runner' column)
        $manager = $defaultManager;
        if (! $manager) {
            // Try to find a manager from the system
            $manager = User::where('email', 'like', '%@corvmc.org')->first();
        }

        return [
            'title' => $title,
            'date' => $date,
            'start_datetime' => $showStart,
            'end_datetime' => $showEnd,
            'touring_bands' => $touringBands,
            'local_bands' => $localBands,
            'cover' => $coverStr,
            'ticket_price' => $ticketPrice,
            'notes' => $notes,
            'manager' => $manager,
            'poster_link' => trim($data['Poster Link'] ?? ''),
        ];
    }

    /**
     * Create an event from parsed data.
     */
    private function createProduction(array $data): Event
    {
        $event = Event::create([
            'title' => $data['title'],
            'description' => $this->buildDescription($data),
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'doors_datetime' => $data['start_datetime']->copy()->subMinutes(30), // Doors 30 min before
            'location' => LocationData::cmc(), // Default to CMC location
            'ticket_price' => $data['ticket_price'],
            'status' => 'scheduled',
            'published_at' => $data['date']->isFuture() ? null : $data['date'],
            'organizer_id' => $data['manager']?->id,
        ]);

        // Add notes if present
        if ($data['notes']) {
            $event->description .= "\n\nNotes: ".$data['notes'];
            $event->save();
        }

        // TODO: Could enhance to create/link band relationships
        // This would require more complex logic to match band names to existing bands

        return $event;
    }

    /**
     * Build production description from band data.
     */
    private function buildDescription(array $data): string
    {
        $description = '';

        if (! empty($data['touring_bands'])) {
            $description .= 'Featuring touring acts: '.implode(', ', $data['touring_bands']);
        }

        if (! empty($data['local_bands'])) {
            if ($description) {
                $description .= "\n\n";
            }
            $description .= 'With local support from: '.implode(', ', $data['local_bands']);
        }

        if ($data['cover']) {
            if ($description) {
                $description .= "\n\n";
            }
            $description .= 'Cover: '.$data['cover'];
        }

        return $description ?: 'Live music at Corvallis Music Collective';
    }

    /**
     * Parse bands from a string.
     */
    private function parseBands(string $bandStr): array
    {
        if (empty($bandStr) || strtolower(trim($bandStr)) === 'tbd') {
            return [];
        }

        // Split by commas and clean up
        $bands = explode(',', $bandStr);
        $bands = array_map('trim', $bands);
        $bands = array_filter($bands);

        // Remove empty entries and common non-band text
        $bands = array_filter($bands, function ($band) {
            $lower = strtolower($band);

            return ! in_array($lower, ['', 'tbd', 'n/a', 'na', 'none']);
        });

        return array_values($bands);
    }

    /**
     * Parse cover charge to extract numeric price.
     */
    private function parseCoverCharge(string $coverStr): ?float
    {
        if (empty($coverStr)) {
            return null;
        }

        // Extract first dollar amount from string like "$10", "$7-10", etc.
        if (preg_match('/\$(\d+)/', $coverStr, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Parse time string like "7:00 PM" to hour/minute array.
     */
    private function parseTime(string $timeStr): array
    {
        $timeStr = trim($timeStr);

        if (empty($timeStr)) {
            return ['hour' => 19, 'minute' => 0]; // Default 7 PM
        }

        try {
            $time = Carbon::createFromFormat('g:i A', $timeStr);

            return ['hour' => $time->hour, 'minute' => $time->minute];
        } catch (\Exception $e) {
            return ['hour' => 19, 'minute' => 0]; // Default 7 PM
        }
    }

    /**
     * Check if a row should be skipped.
     */
    private function isSkippableRow(array $row): bool
    {
        $firstCell = strtolower(trim($row[0] ?? ''));

        // Skip month headers, empty rows, summary rows
        $skipPatterns = [
            'june', 'july', 'august', 'september', 'october', 'november', 'december',
            'do not book', '#value!', 'total', 'summary',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($firstCell, $pattern)) {
                return true;
            }
        }

        // Skip if mostly empty
        $nonEmptyCells = array_filter($row, fn ($cell) => ! empty(trim($cell)));

        return count($nonEmptyCells) < 2;
    }

    /**
     * Check if date string is valid.
     */
    private function isValidDate(string $dateStr): bool
    {
        try {
            Carbon::createFromFormat('m/d/Y', $dateStr);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
