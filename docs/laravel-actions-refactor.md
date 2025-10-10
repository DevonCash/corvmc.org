# Proposal: Migrate from Service Layer to Laravel Actions v2

**Status:** 📝 Proposal for Discussion
**Created:** 2025-01-10
**Impact:** High - Architectural change affecting all business logic

## Problem Statement

The current service layer architecture is becoming unwieldy:

1. **God Object Services**: Services like `ReservationService` and `VolunteerService` have grown to 20+ methods covering multiple responsibilities
2. **Unclear Boundaries**: When should logic go in service vs. model vs. controller?
3. **Testing Complexity**: Testing services requires mocking many dependencies
4. **Reusability**: Services are singletons - can't easily queue them, dispatch them as jobs, or use them as event listeners
5. **Discovery**: Finding where specific logic lives requires searching through large service files

### Current Service Examples

**ReservationService** (995 lines):

- createReservation()
- checkConflicts()
- calculateCost()
- deductCredits()
- createCheckoutSession()
- cancelReservation()
- sendReminders()
- getUpcomingReservations()
- calculateFreeHours()
- etc.

**VolunteerService** (994 lines):

- signUpForShift()
- cancelSignup()
- completeShift()
- markNoShow()
- awardExceptionalPerformance()
- checkSustainingMemberEligibility()
- updateAllSustainingMemberStatuses()
- logManualHours()
- getTotalHours()
- generateShiftsFromProduction()
- etc.

## Proposed Solution: Laravel Actions v2

**Package:** `lorisleiva/laravel-actions` v2

### What Are Actions?

Actions are single-purpose, executable classes that represent one business operation. Each action:

- Has one clear responsibility (Single Responsibility Principle)
- Can run as: object call, job, listener, command, controller
- Is easy to test in isolation
- Has clear inputs (constructor/handle params) and outputs (return value)
- Can be queued, retried, rate-limited out of the box

### Example: Before vs. After

#### Before (Service Method)

```php
// app/Services/ReservationService.php (singleton)
class ReservationService
{
    public function createReservation(
        User $user,
        Carbon $startTime,
        int $durationBlocks,
        bool $useCredits = true
    ): Reservation {
        // 50+ lines of business logic
        // Conflict checking
        // Credit deduction
        // Transaction creation
        // Notification sending
    }

    // ... 20 more methods
}

// Usage in controller:
$reservation = app(ReservationService::class)->createReservation(...);
```

#### After (Action)

```php
// app/Actions/Reservations/CreateReservation.php
class CreateReservation extends Action
{
    public function handle(
        User $user,
        Carbon $startTime,
        int $durationBlocks,
        bool $useCredits = true
    ): Reservation {
        // Same business logic, but isolated
        // Can call other actions as needed:
        // CheckReservationConflicts::run($startTime, ...);
        // DeductUserCredits::run($user, $blocks);
    }
}

// Usage in controller:
$reservation = CreateReservation::run($user, $startTime, $blocks);

// Can also queue it:
CreateReservation::dispatch($user, $startTime, $blocks);

// Or use as event listener:
Event::listen(ReservationRequested::class, CreateReservation::class);

// Or as console command (automatic):
php artisan reservation:create {user} {startTime} {blocks}
```

## Migration Strategy

### Phase 1: Establish Patterns (Week 1)

1. **Install Package**

```bash
composer require lorisleiva/laravel-actions
php artisan vendor:publish --tag=actions-config
```

2. **Create Action Structure**

```
app/Actions/
├── Reservations/
│   ├── CreateReservation.php
│   ├── CancelReservation.php
│   ├── CheckReservationConflicts.php
│   └── CalculateReservationCost.php
├── Credits/
│   ├── AllocateCredits.php
│   ├── DeductCredits.php
│   └── CalculateMonthlyAllocation.php
├── Volunteers/
│   ├── SignUpForShift.php
│   ├── CompleteShift.php
│   ├── MarkNoShow.php
│   └── CheckSustainingMemberEligibility.php
├── Productions/
├── Posters/
└── ...
```

3. **Establish Naming Conventions**

- Actions use imperative verbs: `CreateReservation`, not `ReservationCreator`
- Group by domain: `Reservations/`, `Credits/`, `Volunteers/`
- One action = one responsibility
- Complex operations can call multiple actions

4. **Create Example Migrations**

- Pick 2-3 services to migrate as examples
- Document patterns and best practices
- Create test suite demonstrating new approach

### Phase 2: Incremental Migration (Weeks 2-4)

**Parallel Approach** - Keep services running while building actions:

1. **High-Value Services First**
   - ReservationService (most complex)
   - CreditService (transaction-critical)
   - VolunteerService (many responsibilities)

2. **Migration Process per Service**
   - Create action classes for each service method
   - Actions can call old service methods during transition
   - Update new code to use actions
   - Gradually remove service methods as actions proven stable
   - Delete service when all methods migrated

3. **Keep Services as Facades (Optional)**

```php
// For backward compatibility during migration:
class ReservationService
{
    public function createReservation(...): Reservation
    {
        return CreateReservation::run(...);
    }
}
```

### Phase 3: Update Patterns (Week 5)

1. **Update Filament Resources**

```php
// Before:
use(ReservationService::class)->createReservation($user, ...);

// After:
CreateReservation::run($user, ...);
```

2. **Update Commands**

```php
// Before:
class SendReservationReminders extends Command
{
    public function handle(ReservationService $service)
    {
        $service->sendReminders();
    }
}

// After - actions can BE commands:
class SendReservationReminders extends Action
{
    public string $commandSignature = 'reservations:send-reminders';

    public function handle()
    {
        // Logic here
    }
}
```

3. **Update Tests**

```php
// Before - must mock service:
$this->mock(ReservationService::class)
    ->shouldReceive('createReservation')
    ->andReturn($reservation);

// After - test action directly:
$reservation = CreateReservation::run($user, $startTime, 4);
$this->assertDatabaseHas('reservations', [...]);
```

### Phase 4: Cleanup (Week 6)

1. Remove empty service classes
2. Remove service registrations from AppServiceProvider
3. Update documentation (CLAUDE.md)
4. Final testing pass

## Action Organization Patterns

### Domain-Driven Structure

```
app/Actions/
├── Reservations/       # Practice space reservations
├── Credits/            # Credit system
├── Volunteers/         # Volunteer management
├── Productions/        # Event productions
├── Posters/           # Poster printing
├── Distributions/     # Distribution runs
├── Memberships/       # Member subscriptions
├── Payments/          # Payment processing
└── Notifications/     # Notification sending
```

### Shared/Utility Actions

```
app/Actions/
├── Shared/
│   ├── SendEmail.php
│   ├── UploadFile.php
│   ├── GenerateQrCode.php
│   └── GeocodeAddress.php
```

### Action Composition

Actions can call other actions:

```php
class CreateReservation extends Action
{
    public function handle(User $user, Carbon $start, int $blocks): Reservation
    {
        // Check conflicts (separate action)
        CheckReservationConflicts::run($start, $blocks);

        // Calculate cost (separate action)
        $cost = CalculateReservationCost::run($user, $blocks);

        // Deduct credits if applicable (separate action)
        if ($user->wantsToUseCredits()) {
            DeductUserCredits::run($user, $blocks);
        }

        // Create reservation
        $reservation = Reservation::create([...]);

        // Send notification (separate action)
        SendReservationConfirmation::run($reservation);

        return $reservation;
    }
}
```

## Advanced Features

### 1. As Queueable Jobs

```php
// Synchronous:
CreateReservation::run($user, $startTime, 4);

// Queued (automatically becomes job):
CreateReservation::dispatch($user, $startTime, 4);

// With queue options:
CreateReservation::dispatch($user, $startTime, 4)
    ->onQueue('reservations')
    ->delay(now()->addMinutes(5));
```

### 2. As Event Listeners

```php
class SendReservationConfirmation extends Action
{
    public function asListener(): array
    {
        return [
            ReservationCreated::class => 'handle',
        ];
    }

    public function handle(ReservationCreated $event): void
    {
        $event->reservation->user->notify(
            new ReservationConfirmationNotification($event->reservation)
        );
    }
}
```

### 3. As Console Commands

```php
class UpdateSustainingMemberStatuses extends Action
{
    public string $commandSignature = 'volunteer:update-sustaining-members {--dry-run}';
    public string $commandDescription = 'Update sustaining member status based on volunteer hours';

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        // ... logic
    }
}
```

### 4. As Form Requests (for Filament)

```php
class CreateReservation extends Action
{
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'start_time' => 'required|date|after:now',
            'duration_blocks' => 'required|integer|min:2|max:16',
        ];
    }

    public function handle(User $user, Carbon $startTime, int $blocks): Reservation
    {
        // Validation already happened
    }
}
```

## Benefits

### Code Organization

- ✅ Smaller, focused classes (100-200 lines vs 1000+ line services)
- ✅ Clear separation of concerns
- ✅ Easy to locate specific business logic
- ✅ Self-documenting code (class name = what it does)

### Testability

- ✅ Test actions in isolation without complex mocking
- ✅ Can test as unit (run directly) or integration (with dependencies)
- ✅ Easier to mock individual actions than entire services

### Flexibility

- ✅ Run as sync, queued, listener, command without code changes
- ✅ Compose complex operations from simple actions
- ✅ Queue portions of workflow easily
- ✅ Retry failed actions automatically

### Developer Experience

- ✅ IDE autocomplete works better with focused classes
- ✅ Easier onboarding (actions are self-contained)
- ✅ Less decision fatigue (one action = one responsibility)
- ✅ Discover functionality by browsing action directories

### Performance

- ✅ Queue heavy operations easily
- ✅ Rate limit actions individually
- ✅ Batch processing built-in
- ✅ Better control over transaction boundaries

## Tradeoffs

### Initial Migration Effort

- ⚠️ Breaking up services requires thought and planning
- ⚠️ Need to establish conventions early
- ⚠️ Team needs to learn new patterns
- **Mitigation**: Parallel approach, migrate incrementally, keep services as facades initially

### More Files

- ⚠️ 100 service methods → 100 action classes
- ⚠️ Can feel overwhelming initially
- **Mitigation**: Good organization, clear naming, IDE navigation

### Potential Over-Granularity

- ⚠️ Risk of creating too many tiny actions
- ⚠️ Can lead to deep nesting (action calls action calls action)
- **Mitigation**: Establish guidelines (actions should do meaningful work, not just wrap one line)

### Learning Curve

- ⚠️ Different mental model from services
- ⚠️ Need to learn package-specific features
- **Mitigation**: Good documentation, examples, code reviews

## Action Design Guidelines

### When to Create an Action

**Create an action when:**

- ✅ Logic represents a distinct business operation
- ✅ Operation might need to run async/queued
- ✅ Logic will be reused in multiple places
- ✅ Operation has clear inputs and outputs
- ✅ You can name it with a clear imperative verb

**Don't create an action for:**

- ❌ Simple getters/setters
- ❌ Pure data transformations (use DTOs)
- ❌ One-line operations
- ❌ Framework boilerplate (use models, controllers normally)

### Action Size

**Good action size:** 50-200 lines

- Clear handle() method
- Focused responsibility
- Calls 2-5 other actions/services max

**Too small:** < 20 lines

- Probably just a helper function
- Consider putting in model or trait

**Too large:** > 300 lines

- Break into multiple actions
- Extract sub-operations

### Action Naming

**Format:** `{Verb}{Noun}` or `{Verb}{Noun}{PrepositionalPhrase}`

**Good names:**

- `CreateReservation`
- `CancelReservation`
- `SendReservationReminder`
- `CheckReservationConflicts`
- `DeductCreditsFromUser`
- `GenerateShiftsFromProduction`

**Bad names:**

- `ReservationCreator` (noun-focused)
- `HandleReservation` (vague verb)
- `DoStuff` (meaningless)
- `ReservationManager` (too broad)

## Implementation Estimate

### Phase 1: Setup & Patterns (Week 1)

**Effort:** 8-12 hours

- Install package and configure
- Create directory structure
- Migrate 1 small service as example (CreditService)
- Document patterns and conventions
- Team training/discussion

### Phase 2: Core Services (Weeks 2-3)

**Effort:** 30-40 hours

- ReservationService → ~15 actions (12-16 hours)
- VolunteerService → ~12 actions (10-12 hours)
- ProductionService → ~8 actions (6-8 hours)
- Update Filament resources to use actions (2-4 hours)

### Phase 3: Supporting Services (Week 4)

**Effort:** 20-28 hours

- PaymentService → ~6 actions (4-6 hours)
- MemberBenefitsService → ~4 actions (3-4 hours)
- EquipmentService → ~5 actions (4-5 hours)
- PosterService → ~8 actions (6-8 hours)
- BandService → ~4 actions (3-5 hours)

### Phase 4: Testing & Cleanup (Week 5)

**Effort:** 12-16 hours

- Update/write tests for actions (8-12 hours)
- Remove old service files (2-3 hours)
- Update documentation (2-3 hours)

### Phase 5: Commands & Jobs (Week 6)

**Effort:** 8-12 hours

- Convert commands to actions (4-6 hours)
- Update scheduled tasks (2-3 hours)
- Convert jobs to actions (2-3 hours)

**Total Estimate:** 78-108 hours (2-2.5 weeks full-time, or 4-6 weeks part-time)

## Success Metrics

### Code Quality

- Average class size < 200 lines
- Service layer completely removed
- 100% of business logic in actions

### Developer Experience

- Reduced time to find logic (measure via surveys)
- Easier onboarding for new developers
- Increased test coverage (easier to test)

### Performance

- More operations queued (track queue job count)
- Reduced request times (async operations)

### Maintainability

- Fewer merge conflicts (smaller files)
- Easier refactoring (isolated actions)

## Recommendation

**Proceed with migration** for the following reasons:

1. **Timing**: Better to refactor now than when codebase is 2x larger
2. **Alignment**: Actions align with existing principles (single responsibility, testability)
3. **Future-Proofing**: Makes queueing, event-driven architecture easier later
4. **Developer Experience**: Clearer code organization improves velocity
5. **Package Maturity**: Laravel Actions v2 is stable and widely used

## Alternative: Hybrid Approach

If full migration feels too aggressive, consider **hybrid approach**:

1. **Keep existing services** for now
2. **New features use actions** exclusively
3. **Gradually extract** most complex service methods to actions over time
4. **Services become thin wrappers** around actions

This reduces risk but delays full benefits.

## Questions for Discussion

1. **Timeline**: Do we have 4-6 weeks to dedicate to this migration?
2. **Team Buy-In**: Is everyone comfortable learning a new pattern?
3. **Risk Tolerance**: Prefer big-bang migration or incremental hybrid?
4. **Priority**: Does this take precedence over new features?
5. **Testing**: What's our testing strategy during migration?

## Next Steps If Approved

1. Install package and configure
2. Create proof-of-concept with CreditService (smallest, clearest)
3. Review POC as team
4. Decide on full migration vs. hybrid
5. Create detailed migration checklist
6. Begin phased rollout

## References

- Laravel Actions Package: <https://laravelactions.com/>
- Package Repo: <https://github.com/lorisleiva/laravel-actions>
- "Organizing Business Logic": <https://martinfowler.com/bliki/AnemicDomainModel.html>
- "Action Oriented Programming": <https://freek.dev/2207-simplifying-controllers-with-laravel-actions>
