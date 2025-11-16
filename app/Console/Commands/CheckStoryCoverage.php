<?php

namespace App\Console\Commands;

use App\Attributes\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

class CheckStoryCoverage extends Command
{
    protected $signature = 'check:story-coverage {--missing : Show only missing coverage} {--yaml= : Path to user stories YAML file} {--debug : Show debug information}';

    protected $description = 'Check test coverage against user story acceptance criteria from YAML file';

    protected Collection $stories;

    protected Collection $tests;

    protected Collection $coverage;

    public function handle(): int
    {
        $this->info('🧪 Checking User Story Test Coverage');
        $this->line('=====================================');

        $yamlPath = $this->option('yaml') ?: base_path('docs/user-stories.yaml');

        if (! File::exists($yamlPath)) {
            $this->error("User stories YAML file not found at: {$yamlPath}");
            $this->line('Please create the YAML file first or specify the path with --yaml option');

            return 1;
        }

        $this->stories = $this->loadStoriesFromYaml($yamlPath);
        $this->tests = $this->parseTestFiles();
        $this->coverage = $this->analyzeCoverage();

        $this->displayCoverageReport();

        return 0;
    }

    protected function loadStoriesFromYaml(string $yamlPath): Collection
    {
        $yamlContent = File::get($yamlPath);
        $storiesData = Yaml::parse($yamlContent);

        $stories = collect();

        foreach ($storiesData as $index => $storyData) {
            $storySlug = Str::kebab($storyData['name']);
            $storyNumber = $index + 1;

            // Convert YAML structure to our internal format
            $story = [
                'address' => $storySlug,
                'name' => $storyData['name'],
                'number' => $storyNumber,
                'role' => $storyData['As a'] ?? null,
                'want' => $storyData['I want'] ?? null,
                'so_that' => $storyData['So that'] ?? null,
                'acceptance_criteria' => [],
            ];

            // Process acceptance criteria
            if (isset($storyData['criteria']) && is_array($storyData['criteria'])) {
                foreach ($storyData['criteria'] as $criteriaIndex => $criterionText) {
                    $story['acceptance_criteria'][] = [
                        'number' => $criteriaIndex + 1,
                        'text' => $criterionText,
                        'address' => "{$storySlug}.".($criteriaIndex + 1),
                        'covered' => false,
                        'tests' => [],
                    ];
                }
            }

            $stories->push($story);
        }

        $this->info("📖 Loaded {$stories->count()} user stories from YAML file");

        return $stories;
    }

    protected function parseTestFiles(): Collection
    {
        $tests = collect();
        $testFiles = File::glob(base_path('tests/**/*Test.php'));

        foreach ($testFiles as $file) {
            // Try reflection first for PHPUnit test classes
            $reflectionTests = $this->parseTestMethodsWithReflection($file);
            if ($reflectionTests->isNotEmpty()) {
                $tests = $tests->merge($reflectionTests);
            } else {
                // Fall back to regex parsing for Pest tests
                $regexTests = $this->parseTestMethodsWithRegex($file);
                $tests = $tests->merge($regexTests);
            }
        }

        $this->info("🧪 Found {$tests->count()} test methods in ".count($testFiles).' test files');

        if ($this->option('debug')) {
            $testsWithAddresses = $tests->filter(fn ($test) => ! empty($test['addresses']));
            $this->line("🔍 Tests with story addresses: {$testsWithAddresses->count()}");
            foreach ($testsWithAddresses as $test) {
                $addresses = implode(', ', $test['addresses']);
                $this->line("  - {$test['file']}::{$test['method']} -> {$addresses}");
            }
        }

        return $tests;
    }

    protected function parseTestMethodsWithReflection(string $filepath): Collection
    {
        $tests = collect();
        $relativePath = str_replace(base_path().'/', '', $filepath);

        // Extract class name from file path
        $className = $this->getClassNameFromFile($filepath);
        if (! $className || ! class_exists($className)) {
            return $tests;
        }

        try {
            $reflection = new ReflectionClass($className);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                // Skip if not a test method
                if (! $this->isTestMethod($method)) {
                    continue;
                }

                $storyAddresses = $this->getStoryAddressesFromReflection($method);

                $tests->push([
                    'file' => $relativePath,
                    'method' => $method->getName(),
                    'name' => $this->humanizeTestName($method->getName()),
                    'addresses' => $storyAddresses,
                ]);
            }
        } catch (\Exception $e) {
            // If reflection fails, return empty collection to fall back to regex
            return collect();
        }

        return $tests;
    }

    protected function parseTestMethodsWithRegex(string $filepath): Collection
    {
        $tests = collect();
        $content = File::get($filepath);
        $relativePath = str_replace(base_path().'/', '', $filepath);

        // Extract test methods - PHPUnit and Pest formats
        $patterns = [
            '/(?:#\[Test\]\s+)?public function (test_\w+|\w+)\(\)/',  // PHPUnit methods
            '/it\([\'"]([^"\']+)[\'"]/',  // Pest it() functions
            '/test\([\'"]([^"\']+)[\'"]/', // Pest test() functions
        ];

        $methods = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (! empty($matches[1])) {
                $methods = array_merge($methods, $matches[1]);
            }
        }

        foreach ($methods as $method) {
            $tests->push([
                'file' => $relativePath,
                'method' => $method,
                'name' => $this->humanizeTestName($method),
                'addresses' => $this->extractAddressesFromMethod($content, $method),
            ]);
        }

        return $tests;
    }

    protected function humanizeTestName(string $method): string
    {
        // Remove test_ prefix and convert snake_case to readable text
        $name = str_starts_with($method, 'test_') ? substr($method, 5) : $method;

        return str_replace('_', ' ', Str::snake($name));
    }

    protected function extractAddressesFromMethod(string $content, string $method): array
    {
        // Look for various patterns that might reference story addresses
        $patterns = [
            '/(?:@covers|@story)\s+([a-z0-9-]+\.\d+)/',
            '/\/\*\*.*?@story\s+([a-z0-9-]+\.\d+)/s',
            '/\/\/\s*@story\s+([a-z0-9-]+\.\d+)/',
            '/\/\/.*?story:?\s+([a-z0-9-]+\.\d+)/i',
            // PHP attributes for Story class
            '/#\[Story\([\'"]([a-z0-9-]+\.\d+)[\'"]\)\]/',
            '/#\[Story\(\[([^\]]+)\]\)\]/', // For arrays like #[Story(['story.1', 'story.2'])]
        ];

        // Get the method content and some context before it (for docblocks)
        // Handle both PHPUnit methods and Pest test functions
        $searchPatterns = [
            "function {$method}(",
            "it('{$method}'",
            'it("'.$method.'"',
            "test('{$method}'",
            'test("'.$method.'"',
        ];

        $methodStart = false;
        foreach ($searchPatterns as $pattern) {
            $methodStart = strpos($content, $pattern);
            if ($methodStart !== false) {
                break;
            }
        }

        if ($methodStart === false) {
            return [];
        }

        // Look backwards for the start of the method (including docblocks)
        $searchStart = max(0, $methodStart - 1000);
        $prevMethodEnd = strrpos(substr($content, $searchStart, $methodStart - $searchStart), '}');
        if ($prevMethodEnd !== false) {
            $searchStart = $searchStart + $prevMethodEnd + 1;
        }

        $methodEnd = strpos($content, "\n    }", $methodStart);
        if ($methodEnd === false) {
            $methodEnd = strlen($content);
        }

        $methodContent = substr($content, $searchStart, $methodEnd - $searchStart + 100);

        $addresses = [];
        foreach ($patterns as $index => $pattern) {
            preg_match_all($pattern, $methodContent, $matches);
            if (! empty($matches[1])) {
                // Handle array syntax for attributes
                if ($index === 5) { // Array pattern
                    foreach ($matches[1] as $arrayMatch) {
                        // Extract individual strings from array syntax
                        preg_match_all('/[\'"]([a-z0-9-]+\.\d+)[\'"]/', $arrayMatch, $arrayMatches);
                        if (! empty($arrayMatches[1])) {
                            $addresses = array_merge($addresses, $arrayMatches[1]);
                        }
                    }
                } else {
                    $addresses = array_merge($addresses, $matches[1]);
                }
            }
        }

        return array_unique($addresses);
    }

    protected function getClassNameFromFile(string $filepath): ?string
    {
        $content = File::get($filepath);

        // Extract namespace
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        $namespace = $namespaceMatches[1] ?? '';

        // Extract class name
        preg_match('/class\s+(\w+)/', $content, $classMatches);
        $className = $classMatches[1] ?? '';

        if (! $className) {
            return null;
        }

        return $namespace ? $namespace.'\\'.$className : $className;
    }

    protected function isTestMethod(ReflectionMethod $method): bool
    {
        // Check if method name starts with 'test'
        if (str_starts_with($method->getName(), 'test')) {
            return true;
        }

        // Check for #[Test] attribute
        $attributes = $method->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'PHPUnit\\Framework\\Attributes\\Test') {
                return true;
            }
        }

        return false;
    }

    protected function getStoryAddressesFromReflection(ReflectionMethod $method): array
    {
        $addresses = [];

        $storyAttributes = $method->getAttributes(Story::class);
        foreach ($storyAttributes as $attribute) {
            $storyInstance = $attribute->newInstance();
            $addresses = array_merge($addresses, $storyInstance->getAddresses());
        }

        return $addresses;
    }

    protected function analyzeCoverage(): Collection
    {
        $coverage = collect();

        foreach ($this->stories as &$story) {
            $storyCoverage = [
                'story' => &$story,
                'covered_criteria' => 0,
                'total_criteria' => count($story['acceptance_criteria']),
                'coverage_percentage' => 0,
                'missing_criteria' => [],
            ];

            foreach ($story['acceptance_criteria'] as &$criterion) {
                $coveringTests = $this->tests->filter(function ($test) use ($criterion) {
                    return in_array($criterion['address'], $test['addresses']);
                });

                if ($coveringTests->isNotEmpty()) {
                    $criterion['covered'] = true;
                    $criterion['tests'] = $coveringTests->toArray();
                    $storyCoverage['covered_criteria']++;
                } else {
                    $storyCoverage['missing_criteria'][] = $criterion;
                }
            }

            if ($storyCoverage['total_criteria'] > 0) {
                $storyCoverage['coverage_percentage'] = round(
                    ($storyCoverage['covered_criteria'] / $storyCoverage['total_criteria']) * 100,
                    1
                );
            }

            $coverage->push($storyCoverage);
        }

        return $coverage;
    }

    protected function displayCoverageReport(): void
    {
        $this->line('');
        $this->info('📊 Coverage Report');
        $this->line('==================');

        $totalCriteria = $this->coverage->sum('total_criteria');
        $coveredCriteria = $this->coverage->sum('covered_criteria');
        $overallCoverage = $totalCriteria > 0 ? round(($coveredCriteria / $totalCriteria) * 100, 1) : 0;

        $this->line("Overall Coverage: {$overallCoverage}% ({$coveredCriteria}/{$totalCriteria})");
        $this->line('');

        $missingOnly = $this->option('missing');

        foreach ($this->coverage as $story) {
            if ($missingOnly && $story['coverage_percentage'] == 100) {
                continue;
            }

            $color = $story['coverage_percentage'] >= 80 ? 'info' : ($story['coverage_percentage'] >= 50 ? 'comment' : 'error');

            $this->line("📄 {$story['story']['name']}: {$story['coverage_percentage']}% ({$story['covered_criteria']}/{$story['total_criteria']})", $color);

            if (! empty($story['missing_criteria'])) {
                foreach ($story['missing_criteria'] as $missing) {
                    $this->line("    ❌ {$missing['address']}: {$missing['text']}", 'error');
                }
            }

            if (! $missingOnly) {
                foreach ($story['story']['acceptance_criteria'] as $criterion) {
                    if ($criterion['covered']) {
                        $testCount = count($criterion['tests']);
                        $this->line("    ✅ {$criterion['address']}: {$criterion['text']} ({$testCount} test".($testCount !== 1 ? 's' : '').')', 'info');
                    }
                }
            }

            $this->line('');
        }
    }
}
