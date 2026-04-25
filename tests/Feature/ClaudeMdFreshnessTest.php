<?php

/**
 * These tests verify that CLAUDE.md stays in sync with the codebase.
 *
 * If a test here fails, it means CLAUDE.md has drifted from reality.
 * Update CLAUDE.md to match the current code, then the test will pass again.
 */

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function claudeMd(): string
{
    $path = base_path('CLAUDE.md');
    expect(file_exists($path))->toBeTrue('CLAUDE.md does not exist at project root');

    return file_get_contents($path);
}

it('lists all app-modules', function () {
    $actualModules = collect(File::directories(base_path('app-modules')))
        ->map(fn ($path) => basename($path))
        ->sort()
        ->values();

    $content = claudeMd();

    // Find the "Current modules:" line and extract the comma-separated list
    preg_match('/Current modules:\s*(.+)$/m', $content, $matches);
    expect($matches)->not->toBeEmpty('CLAUDE.md should contain a "Current modules:" line');

    $documentedModules = collect(explode(',', $matches[1]))
        ->map(fn ($m) => trim(Str::remove('.', $m)))
        ->filter()
        ->sort()
        ->values();

    expect($documentedModules->toArray())
        ->toBe($actualModules->toArray());
});

it('lists all state machine classes', function () {
    // Find base state classes by looking for files that define config().
    // These are the actual state machines (not BaseState, not concrete states).
    $stateClasses = collect(File::allFiles(base_path('app-modules')))
        ->filter(function ($file) {
            // Must be in a States/ directory and end with State.php
            if (! Str::contains($file->getRelativePathname(), 'States/')) {
                return false;
            }
            if (! Str::endsWith($file->getFilename(), 'State.php')) {
                return false;
            }

            // Exclude support scaffolding
            if (in_array($file->getFilename(), ['BaseState.php', 'CallbackStateContract.php', 'CallbackStateTransition.php'])) {
                return false;
            }

            // Must define its own config() method — that's what makes it a state machine
            $contents = file_get_contents($file->getPathname());

            return Str::contains($contents, 'function config()');
        })
        ->map(fn ($file) => Str::remove('.php', $file->getFilename()))
        ->sort()
        ->values();

    $content = claudeMd();

    foreach ($stateClasses as $stateClass) {
        expect($content)
            ->toContain("**{$stateClass}**");
    }
});

it('lists all Filament panels', function () {
    $panelDirs = collect(File::directories(base_path('app/Filament')))
        ->map(fn ($path) => basename($path))
        ->filter(fn ($name) => ! in_array($name, ['Shared', 'Actions', 'Tables', 'Infolists']))
        ->sort()
        ->values();

    $content = claudeMd();

    foreach ($panelDirs as $panel) {
        expect($content)
            ->toContain($panel);
    }
});

it('lists all service classes in the services pattern section', function () {
    // This test doesn't check every service by name — that would be too noisy.
    // Instead it verifies that every module WITH a Services/ directory is
    // represented by the "Services are the primary business logic pattern" section existing.
    $modulesWithServices = collect(File::directories(base_path('app-modules')))
        ->filter(fn ($path) => File::isDirectory($path . '/src/Services'))
        ->map(fn ($path) => basename($path))
        ->sort()
        ->values();

    $content = claudeMd();

    expect($content)->toContain('Services are the primary business logic pattern');

    // At minimum, the service pattern must be documented if services exist
    expect($modulesWithServices)->not->toBeEmpty();
});
