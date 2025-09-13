# Code Coverage Analysis

This project has code coverage analysis set up using Xdebug to help you understand how much of your code is tested.

## Prerequisites

- **Xdebug** must be installed and configured
- Run `php -m | grep xdebug` to verify it's installed

## Running Coverage Analysis

### Quick Coverage Commands

```bash
# Run tests with coverage summary
composer test:coverage

# Generate detailed HTML coverage report
composer test:coverage-html

# Show coverage in terminal
composer test:coverage-text
```

### Manual Coverage Commands

```bash
# With environment variable (if composer commands don't work)
XDEBUG_MODE=coverage php artisan test --coverage

# Generate HTML report manually
XDEBUG_MODE=coverage php artisan test --coverage --coverage-html=coverage/html
```

## Understanding Coverage Results

### Coverage Output

- **Green numbers**: Well-covered code (>80%)
- **Yellow numbers**: Moderately covered code (50-80%)  
- **Red numbers**: Poorly covered code (<50%)
- **Total Coverage**: Overall percentage across all files

### Current Status

```
Total Coverage: 10.2%
```

### Well-Tested Areas (>80% coverage)

- ✅ **Data Classes**: ContactData (100%), LocationData (70%)
- ✅ **Models**: MemberProfileResource (80%), ReportResource (81.8%)
- ✅ **Services**: MemberProfileService (90%), ReservationService (57.3%)
- ✅ **Notifications**: ReservationCreatedNotification (94.7%)

### Areas Needing Tests (0% coverage)

- ❌ **Facades**: All service facades (0%)
- ❌ **Filament Pages**: Most admin panel pages (0%)
- ❌ **Controllers**: API and web controllers (0%)
- ❌ **Livewire Components**: Frontend components (0%)
- ❌ **Policies**: Authorization policies (0%)

## Viewing Detailed Coverage

### HTML Report

After running `composer test:coverage-html`, open:

```
coverage/html/index.html
```

This provides:

- **File-by-file** coverage breakdown
- **Line-by-line** highlighting of covered/uncovered code
- **Interactive navigation** through your codebase
- **Method-level** coverage statistics

### Text Report

The text report shows:

- Coverage percentage per file
- Line ranges that are uncovered
- Summary statistics

## Coverage Configuration

Coverage is configured in `phpunit.xml`:

```xml
<coverage>
    <report>
        <html outputDirectory="coverage/html"/>
        <text outputFile="coverage/coverage.txt"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
</coverage>
```

### Excluded Files

The following files are excluded from coverage analysis:

- `app/Exceptions/` - Exception handlers
- `app/Console/Commands/` - Artisan commands  
- `app/Providers/` - Service providers
- `app/Http/Kernel.php` - HTTP kernel

## Coverage Goals

### Target Coverage Levels

- **Critical Business Logic**: >95% (Services, Models)
- **Application Logic**: >80% (Controllers, Resources)
- **UI Components**: >60% (Livewire, Filament)
- **Overall Project**: >75%

### Priority Areas for Testing

1. **Services** - Core business logic (UserSubscriptionService, ReservationService)
2. **Models** - Data layer and relationships
3. **Controllers** - API and webhook endpoints
4. **Filament Resources** - Admin panel functionality

## Integration with CI/CD

You can integrate coverage into your CI/CD pipeline:

```yaml
# GitHub Actions example
- name: Run tests with coverage
  run: composer test:coverage-text

- name: Upload coverage to Codecov
  run: bash <(curl -s https://codecov.io/bash)
```

## Best Practices

### Writing Testable Code

- Keep methods small and focused
- Inject dependencies (avoid static calls)
- Separate business logic from framework code
- Use interfaces for external services

### Coverage Strategies

- **Unit Tests**: Focus on individual methods/classes
- **Feature Tests**: Test complete workflows
- **Integration Tests**: Test component interactions
- **Edge Cases**: Test error conditions and boundaries

### Monitoring Coverage

- Run coverage regularly during development
- Set coverage thresholds in CI/CD
- Review uncovered code for testing opportunities
- Focus on critical business logic first

## Troubleshooting

### Common Issues

**"Code coverage driver not available"**

```bash
# Ensure Xdebug is in coverage mode
XDEBUG_MODE=coverage php artisan test --coverage
```

**Slow test execution**

- Coverage adds overhead (3-5x slower)
- Use coverage only when needed
- Consider using PCOV instead of Xdebug for faster coverage

**Memory issues**

```bash
# Increase memory limit if needed
php -d memory_limit=512M artisan test --coverage
```

## Next Steps

1. **Improve Core Coverage**: Focus on Services and Models first
2. **Add Integration Tests**: Test complete user workflows  
3. **Set Coverage Targets**: Aim for 75%+ overall coverage
4. **Automate Coverage**: Integrate into CI/CD pipeline
5. **Monitor Trends**: Track coverage over time
