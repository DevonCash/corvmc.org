# Laravel Actions Pattern Guide

This document outlines the patterns and conventions for using Laravel Actions in this project, based on the proof-of-concept migration of CreditService.

## What Are Actions?

Actions are single-purpose, executable classes that represent one business operation. They replace the service layer pattern with a more focused, composable approach.

**Key Benefits:**
- ✅ Single Responsibility: One action = one operation
- ✅ Easy to Test: No complex mocking required
- ✅ Flexible Execution: Run as object call, job, listener, or command
- ✅ Better Organization: Find logic by operation name, not by large service file
- ✅ Composable: Actions can call other actions

## Directory Structure

```
app/Actions/
├── Credits/              # Credit system operations
│   ├── GetBalance.php
│   ├── AddCredits.php
│   ├── DeductCredits.php
│   ├── AllocateMonthlyCredits.php
│   ├── RedeemPromoCode.php
│   └── ProcessPendingAllocations.php (command)
├── Reservations/         # Reservation operations
├── Productions/          # Production/event operations
├── Payments/            # Payment processing
└── Shared/              # Shared/utility actions
```

## Action Anatomy

### Basic Action

```php
<?php

namespace App\Actions\Credits;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBalance
{
    use AsAction;

    /**
     * Get user's current credit balance.
     */
    public function handle(User $user, string $creditType = 'free_hours'): int
    {
        // Business logic here
        return $balance;
    }
}
```

### Calling an Action

```php
// Static run method
$balance = GetBalance::run($user, 'free_hours');

// As a queued job
GetBalance::dispatch($user, 'free_hours');

// Traditional instantiation
$action = new GetBalance();
$balance = $action->handle($user, 'free_hours');
```

### Action as Console Command

Actions can automatically become Artisan commands:

```php
class ProcessPendingAllocations
{
    use AsAction;

    public string $commandSignature = 'credits:process-allocations {--dry-run}';
    public string $commandDescription = 'Process all pending credit allocations';

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        // Command logic
    }
}
```

## Action Composition

Actions can call other actions to compose complex operations:

```php
class RedeemPromoCode
{
    use AsAction;

    public function handle(User $user, string $code): CreditTransaction
    {
        $promo = PromoCode::where('code', $code)->firstOrFail();

        // Call another action
        $transaction = AddCredits::run(
            $user,
            $promo->credit_amount,
            'promo_code',
            $promo->id
        );

        // Additional logic
        PromoCodeRedemption::create([...]);

        return $transaction;
    }
}
```

## Backward Compatibility

During migration, keep services as thin wrappers:

```php
class CreditService
{
    public function getBalance(User $user, string $creditType = 'free_hours'): int
    {
        return \App\Actions\Credits\GetBalance::run($user, $creditType);
    }

    public function addCredits(...): CreditTransaction
    {
        return \App\Actions\Credits\AddCredits::run(...);
    }
}
```

This allows:
- Gradual migration of calling code
- Existing facades and dependency injection to continue working
- Zero breaking changes during transition

## Testing Actions

Actions are easier to test than services:

```php
it('adds credits to user balance', function () {
    $user = User::factory()->create();

    $transaction = AddCredits::run(
        $user,
        100,
        'test_source',
        null,
        'Test credit addition'
    );

    expect($transaction->amount)->toBe(100);
    expect($transaction->balance_after)->toBe(100);
});
```

**No mocking required!** Test the action directly with real database interactions.

## Design Guidelines

### When to Create an Action

✅ **Create an action when:**
- Logic represents a distinct business operation
- Operation might need to run async/queued
- Logic will be reused in multiple places
- Operation has clear inputs and outputs
- You can name it with a clear imperative verb

❌ **Don't create an action for:**
- Simple getters/setters (use model methods)
- Pure data transformations (use DTOs)
- One-line operations (use helper functions)
- Framework boilerplate

### Action Naming

**Format:** `{Verb}{Noun}` in imperative mood

**Good names:**
- `GetBalance`
- `AddCredits`
- `DeductCredits`
- `AllocateMonthlyCredits`
- `RedeemPromoCode`
- `ProcessPendingAllocations`

**Bad names:**
- `BalanceGetter` (noun-focused)
- `HandleCredits` (vague verb)
- `DoStuff` (meaningless)
- `CreditManager` (too broad)

### Action Size

- **Ideal:** 50-200 lines per action
- **Too small:** < 20 lines (consider helper function)
- **Too large:** > 300 lines (break into multiple actions)

## Proof of Concept: CreditService

### Before (Service)

```php
class CreditService  // 305 lines
{
    public function getBalance(...) { }
    public function addCredits(...) { }
    public function deductCredits(...) { }
    public function allocateMonthlyCredits(...) { }
    public function redeemPromoCode(...) { }
    public function processPendingAllocations(...) { }
    // + 3 protected helper methods
}
```

### After (Actions)

```
app/Actions/Credits/
├── GetBalance.php (24 lines)
├── AddCredits.php (71 lines)
├── DeductCredits.php (49 lines)
├── AllocateMonthlyCredits.php (104 lines)
├── RedeemPromoCode.php (57 lines)
└── ProcessPendingAllocations.php (75 lines)
```

**Results:**
- 65% reduction in service file size (305 → 105 lines)
- 6 focused, testable action classes
- All functionality preserved
- Zero breaking changes (backward compatibility layer)
- ProcessPendingAllocations can now run as console command

## Migration Strategy

### Phase 1: Proof of Concept ✅
- Install laravel-actions package
- Migrate one small service (CreditService)
- Document patterns
- Validate approach

### Phase 2: Core Services (Next)
- ReservationService → ~15 actions
- ProductionService → ~8 actions
- MemberBenefitsService → ~4 actions

### Phase 3: Supporting Services
- PaymentService, BandService, etc.
- Update Filament resources to use actions directly
- Remove service facades where appropriate

### Phase 4: Cleanup
- Remove empty service classes
- Update service registrations
- Update CLAUDE.md with final patterns

## Advanced Features

### As Queue Jobs

```php
// Dispatch to queue
AddCredits::dispatch($user, 100, 'subscription')
    ->onQueue('credits')
    ->delay(now()->addMinutes(5));
```

### As Event Listeners

```php
class SendWelcomeEmail
{
    use AsAction;

    public function asListener(): array
    {
        return [
            UserRegistered::class => 'handle',
        ];
    }

    public function handle(UserRegistered $event): void
    {
        // Send email
    }
}
```

### With Validation

```php
class CreateReservation
{
    use AsAction;

    public function rules(): array
    {
        return [
            'start_time' => 'required|date|after:now',
            'duration_blocks' => 'required|integer|min:2|max:16',
        ];
    }

    public function handle(...): Reservation
    {
        // Validation already passed
    }
}
```

## Resources

- **Package**: [lorisleiva/laravel-actions](https://laravelactions.com/)
- **Migration Plan**: `docs/laravel-actions-refactor.md`
- **This Project POC**: `app/Actions/Credits/`
