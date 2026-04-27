---
name: spec-tests
description: >
  Generate Pest test files from a feature spec produced by the feature-spec
  skill. Reads the spec, extracts workflows and business rules, proposes a test
  plan, then writes drop-in test files for the module. Use this skill whenever
  the user has a design spec or feature doc and wants tests written from it —
  "write tests for this spec", "generate tests from the design doc", "test the
  volunteer spec", "turn this spec into tests", or when they reference a spec
  file and say "now let's write tests." Also use when someone says "what should
  we test for this feature" with a spec in hand. If a feature spec exists and
  the user mentions testing, use this skill.
---

# Spec Tests — Generate Workflow Tests from a Feature Spec

This skill reads a feature spec (the kind produced by the feature-spec skill)
and generates Pest test files that verify the spec's workflows and business
rules. The tests are a behavioral contract — they encode what the spec says the
feature should do, so that implementation drift is caught early.

The tests focus on workflows, not units. A spec says "when a coordinator
confirms a volunteer, the HourLog transitions to Confirmed and a notification
fires." The test calls the service method, asserts the state change, and checks
the notification was sent. It doesn't test that a model has the right fillable
array or that a migration column exists.

## When to use this skill

When someone has a feature spec and wants tests. The spec might be a markdown
file in `docs/`, a design doc from the feature-spec skill, or prose the user
pastes in. The spec needs to describe workflows with enough detail that you can
identify what to call, what should happen, and what shouldn't.

## The process

### 1. Read the spec

Read the spec file. As you read, extract:

- **Workflows** — numbered step sequences ("the event volunteering flow",
  "the self-reported work flow"). Each workflow is a test file or a describe
  block.
- **State machines** — states, transitions, who triggers them, and which
  transitions are invalid. Each valid transition is a test. A few representative
  invalid transitions are tests too.
- **Business rules** — constraints, capacity limits, preconditions, side effects.
  "Only CheckedOut and Approved hours count toward reporting." "Capacity is
  enforced — no double sign-ups." Each rule is a test.
- **Cross-module effects** — events fired, listeners that react. "Volunteering
  fires VolunteerCheckedOut; Finance listens." These are event-dispatch
  assertions.
- **Permission gates** — who can do what. These become authorization tests, but
  only for non-obvious gates. Don't test that a random user can't access a staff
  page — that's boilerplate. Do test that "a confirmed volunteer can self-check-in
  without a special permission" because the gate is the status, not a role.

Also look at the "What changes" and "What doesn't change" sections. "What
doesn't change" can suggest negative tests — "Finance module is unchanged"
means volunteering tests should not create Orders.

### 2. Propose a test plan

Before writing any code, present a test plan. Organize it by workflow or
domain concept, not by file. For each group, list the test descriptions (the
strings that go in `it()` calls) and note what each one verifies.

The plan should be concise — a list of `it('...')` descriptions grouped under
describe blocks, with a one-line note on setup or assertions only when the
intent isn't obvious from the description.

Ask the user to confirm, add, or remove tests before proceeding.

**What makes a good test plan:**

- Every workflow from the spec has at least one happy-path test that walks
  through the full sequence.
- State machines have transition tests for each valid path, plus a few
  representative invalid transitions (not every impossible combination —
  just enough to verify the transition rules are enforced).
- Business rules that constrain behavior ("capacity enforcement", "only
  terminal states count for reporting") each get their own test.
- Side effects (events fired, notifications sent, tags propagated) are
  tested where the spec calls them out.
- The plan doesn't test framework plumbing. No tests for "model has correct
  fillable", "migration creates table", "factory works." Those aren't spec
  behaviors.

### 3. Generate test files

Write the test files. Follow the codebase conventions described below.

## Codebase conventions

### File organization

Tests live in the module's test directory:

```
app-modules/{module}/tests/
├── Feature/
│   ├── Workflows/       # End-to-end workflow tests
│   ├── Models/          # State machine and model behavior tests
│   └── ...
└── Pest.php
```

Workflow tests go in `Feature/Workflows/`. State machine tests go in
`Feature/Models/`. If the module doesn't have these subdirectories yet,
create them. Update the module's `Pest.php` to include new subdirectories:

```php
pest()->extend(Tests\TestCase::class)
    ->in('Feature', 'Feature/Workflows', 'Unit');
```

### Pest syntax and structure

Use Pest's `describe()` / `it()` / `beforeEach()` / `expect()` API. No
PHPUnit class-based tests.

```php
describe('HourLogService::checkIn()', function () {
    it('transitions a Confirmed HourLog to CheckedIn', function () {
        // arrange
        // act
        // assert with expect()
    });
});
```

**Describe blocks** group related tests. Name them after the service method
or workflow step being tested.

**Test descriptions** (`it('...')`) should read as behavioral assertions:
"transitions X to Y", "fires Z event", "rejects when capacity is full."

**beforeEach** for shared setup that every test in a describe block needs:

```php
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->coordinator = User::factory()->withRole('staff')->create();
});
```

### Helper functions

For complex setup that multiple tests share, define file-scoped helper
functions above the describe blocks:

```php
function createShiftWithVolunteer(object $test): array
{
    $position = Position::factory()->create();
    $shift = Shift::factory()->create(['position_id' => $position->id, 'capacity' => 2]);
    $hourLog = HourLog::factory()->create([
        'user_id' => $test->user->id,
        'shift_id' => $shift->id,
        'status' => Interested::class,
    ]);

    return ['position' => $position, 'shift' => $shift, 'hourLog' => $hourLog];
}
```

These functions receive `$test` (the test context, so they can access
`$this->user` etc.) and return an associative array of the created objects.

### State machine testing

Import concrete state classes and test transitions via the service layer
(not by calling `transitionTo` directly, unless testing the state machine
config itself):

```php
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Confirmed;

it('transitions Interested to Confirmed when coordinator confirms', function () {
    ['hourLog' => $hourLog] = createShiftWithVolunteer($this);

    app(HourLogService::class)->confirm($hourLog, $this->coordinator);

    expect($hourLog->fresh()->status)->toBeInstanceOf(Confirmed::class);
});
```

For invalid transitions, assert the exception:

```php
it('rejects direct transition from Interested to CheckedIn', function () {
    ['hourLog' => $hourLog] = createShiftWithVolunteer($this);

    expect(fn () => app(HourLogService::class)->checkIn($hourLog))
        ->toThrow(\Spatie\ModelStates\Exceptions\TransitionNotFound::class);
});
```

### Event assertions

When the spec says a domain event fires, verify it:

```php
it('fires VolunteerConfirmed when coordinator confirms', function () {
    Event::fake([VolunteerConfirmed::class]);

    ['hourLog' => $hourLog] = createShiftWithVolunteer($this);
    app(HourLogService::class)->confirm($hourLog, $this->coordinator);

    Event::assertDispatched(VolunteerConfirmed::class, function ($event) use ($hourLog) {
        return $event->hourLog->id === $hourLog->id;
    });
});
```

### Service layer testing

Tests call service methods, not raw model operations. The spec defines
behavior in terms of actions ("coordinator confirms", "volunteer checks in"),
and services are where those actions live.

```php
// Good — tests the business action
app(HourLogService::class)->confirm($hourLog, $this->coordinator);

// Avoid — tests model plumbing, not behavior
$hourLog->status->transitionTo(Confirmed::class);
```

The exception is when testing state machine configuration directly (e.g.,
"these transitions are allowed, these aren't"). In that case, testing
`transitionTo` is appropriate.

### Factory usage

Use factory states to set up test scenarios. If a factory doesn't exist yet
for a new module, note that it needs to be created but don't generate the
factory in the test file — just reference it. The test files assume factories
exist.

```php
$reservation = RehearsalReservation::factory()->confirmed()->create();
$hourLog = HourLog::factory()->create(['status' => Confirmed::class]);
```

### Money values

All money is in cents. `1500` = $15.00. Use integers in test assertions.

### Assertions style

Use Pest's `expect()` API, not PHPUnit assertions:

```php
// Good
expect($hourLog->fresh()->status)->toBeInstanceOf(CheckedOut::class);
expect($hourLog->minutes)->toBe(120);

// Avoid
$this->assertInstanceOf(CheckedOut::class, $hourLog->fresh()->status);
```

### What NOT to test

- **Framework behavior.** Don't test that Eloquent relationships return the
  right type, that migrations create columns, or that factories produce valid
  models. These are framework guarantees.
- **Filament UI details.** Don't test that a Filament resource renders a
  specific column or that a modal has the right label. Those are UI concerns,
  not spec behaviors.
- **Every invalid transition.** If a state machine has 8 states, there are
  dozens of invalid transitions. Test a representative handful — enough to
  confirm the transition rules are registered, not an exhaustive matrix.
- **Obvious permission checks.** Don't test that an unauthenticated user
  can't access a staff page. Do test permission gates that are unusual or
  subtle (like status-based self-check-in).

## Adapting to what the spec contains

Not every spec has state machines or cross-module events. Adapt:

- **Spec has no state machines:** Focus on workflow sequences and business
  rule tests. Test that services produce the right outcomes and reject
  invalid inputs.
- **Spec is a rework, not greenfield:** The "What changes" table tells you
  what's new. Focus tests on changed behavior. The "What doesn't change"
  table can suggest regression-style negative tests ("this still works the
  same way").
- **Spec has UI-heavy changes:** If the spec is mostly about layout and
  display (like local-resources-rework), there may be fewer behavioral tests
  to write. Focus on data queries (correct filtering, ordering, visibility
  based on published_at) and any new business logic.
- **Spec references existing services:** Read the existing service to
  understand the method signatures. The test should call what exists, not
  invent an API.

## Conversation style

- Read the spec before asking questions. Come to the user with a proposed
  test plan, not a blank page.
- Be concrete. "Here are the 14 tests I'd write" is more useful than "we
  should test the workflows."
- When the user says "that's too many" or "skip the obvious ones," listen.
  They know their domain. If they say the capacity test isn't worth writing,
  it probably isn't.
- Don't explain what you're not testing or why. Just present what you are
  testing.
