<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTablerIcons extends Command
{
    protected $signature = 'icons:sync-tabler {--dry-run : Show what would be copied/removed without making changes}';

    protected $description = 'Sync tabler icons from vendor to local directory based on actual usage in the codebase';

    private const VENDOR_PATH = 'vendor/secondnetwork/blade-tabler-icons/resources/svg';

    private const LOCAL_PATH = 'resources/svg/tabler';

    private const SCAN_DIRS = ['app', 'app-modules', 'resources', 'config'];

    private const SCAN_EXTENSIONS = ['php', 'js'];

    public function handle(): int
    {
        $vendorPath = base_path(self::VENDOR_PATH);
        $localPath = resource_path('svg/tabler');

        if (! File::isDirectory($vendorPath)) {
            $this->error('Vendor tabler icons not found. Run: composer require secondnetwork/blade-tabler-icons');

            return self::FAILURE;
        }

        $usedIcons = $this->findUsedIcons();
        $this->info(sprintf('Found %d unique tabler icons referenced in codebase.', count($usedIcons)));

        if ($this->option('dry-run')) {
            return $this->dryRun($usedIcons, $vendorPath, $localPath);
        }

        File::ensureDirectoryExists($localPath);

        $copied = 0;
        $missing = [];

        foreach ($usedIcons as $icon) {
            $source = $vendorPath.'/'.$icon.'.svg';
            $dest = $localPath.'/'.$icon.'.svg';

            if (! File::exists($source)) {
                $missing[] = $icon;

                continue;
            }

            File::copy($source, $dest);
            $copied++;
        }

        // Remove icons that are no longer used
        $removed = 0;
        foreach (File::files($localPath) as $file) {
            $iconName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if (! in_array($iconName, $usedIcons)) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        $this->info(sprintf('Synced %d icons to %s', $copied, self::LOCAL_PATH));

        if ($removed > 0) {
            $this->info(sprintf('Removed %d unused icons.', $removed));
        }

        if (! empty($missing)) {
            $this->warn('Icons referenced but not found in vendor:');
            foreach ($missing as $icon) {
                $this->warn("  - {$icon}");
            }
        }

        return self::SUCCESS;
    }

    private function dryRun(array $usedIcons, string $vendorPath, string $localPath): int
    {
        $this->info('Dry run — no changes will be made.');
        $this->newLine();

        $wouldCopy = [];
        $missing = [];

        foreach ($usedIcons as $icon) {
            if (File::exists($vendorPath.'/'.$icon.'.svg')) {
                $wouldCopy[] = $icon;
            } else {
                $missing[] = $icon;
            }
        }

        $this->info(sprintf('Would copy %d icons to %s', count($wouldCopy), self::LOCAL_PATH));

        if (File::isDirectory($localPath)) {
            $wouldRemove = 0;
            foreach (File::files($localPath) as $file) {
                $iconName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                if (! in_array($iconName, $usedIcons)) {
                    $wouldRemove++;
                }
            }
            if ($wouldRemove > 0) {
                $this->info(sprintf('Would remove %d unused icons.', $wouldRemove));
            }
        }

        if (! empty($missing)) {
            $this->warn('Icons referenced but not found in vendor:');
            foreach ($missing as $icon) {
                $this->warn("  - {$icon}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function findUsedIcons(): array
    {
        $icons = [];
        $pattern = '/tabler-([a-z0-9]+(?:-[a-z0-9]+)*)/';

        // Scan source files
        foreach (self::SCAN_DIRS as $dir) {
            $path = base_path($dir);
            if (! File::isDirectory($path)) {
                continue;
            }

            $files = File::allFiles($path);
            foreach ($files as $file) {
                if (! in_array($file->getExtension(), self::SCAN_EXTENSIONS)) {
                    continue;
                }

                $contents = $file->getContents();
                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[1] as $icon) {
                        $icons[$icon] = true;
                    }
                }
            }
        }

        // Scan database JSON columns for dynamically-referenced icons
        $this->scanDatabaseIcons($icons, $pattern);

        $iconNames = array_keys($icons);
        sort($iconNames);

        return $iconNames;
    }

    /**
     * @param  array<string, bool>  $icons
     */
    private function scanDatabaseIcons(array &$icons, string $pattern): void
    {
        $tables = [
            'site_pages' => 'blocks',
        ];

        foreach ($tables as $table => $column) {
            try {
                $rows = \Illuminate\Support\Facades\DB::table($table)->pluck($column);
                foreach ($rows as $json) {
                    if ($json && preg_match_all($pattern, $json, $matches)) {
                        foreach ($matches[1] as $icon) {
                            $icons[$icon] = true;
                        }
                    }
                }
            } catch (\Exception) {
                // Table may not exist yet (e.g. fresh install before migrations)
            }
        }
    }
}
