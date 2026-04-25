# CLAUDE.md

Web application for the Corvallis Music Collective — a nonprofit music collective in Corvallis, Oregon. Members book practice spaces, form bands, attend events, and borrow equipment.

## Stack

Laravel 12, Filament v5, Livewire 3, Pest, PostgreSQL (production) / SQLite (dev), Stripe via Laravel Cashier, Vite + Tailwind CSS v4.

## Commands

```bash
composer dev              # Start all dev services (server, queue, logs, vite)
composer test             # Run full test suite (parallel)
composer test:coverage    # With coverage
vendor/bin/pint           # Code style (run before committing)
composer phpstan          # Static analysis
php artisan test --filter=TestName  # Single test
```

## Architecture

### Two-layer modular system (`internachi/modular`)

**Module layer** (`app-modules/`): Self-contained domains. Each module owns its models, services, events, DTOs, state machines, and tests. Modules should not reference other modules' concrete classes — use interfaces, and bind them in `AppServiceProvider`.

**Integration layer** (`app/`): Cross-cutting glue. Policies live here (never in modules). Listeners that react to domain events from modules live here. Observers for cache/side effects live here.

Current modules: bands, equipment, events, finance, kiosk, membership, moderation, space-management, sponsorship, support.

### Module internal structure

```
app-modules/{module}/
├── src/
│   ├── Services/         # Primary business logic (injectable, stateless)
│   ├── Models/           # Domain models
│   ├── States/           # spatie/model-states state machines
│   ├── Events/           # Domain events for cross-module side effects
│   ├── Listeners/        # Intra-module event handlers
│   ├── Data/             # DTOs (spatie/laravel-data)
│   ├── Enums/
│   ├── Concerns/         # Model traits
│   ├── Contracts/        # Interfaces for external dependencies
│   ├── Facades/
│   ├── Notifications/
│   ├── Providers/
│   ├── Actions/          # Legacy (lorisleiva/laravel-actions) — being replaced by Services
│   └── Products/         # (finance only) Product definitions
├── database/
│   ├── migrations/
│   └── factories/
└── tests/
```

### Services are the primary business logic pattern

Services live in `{module}/src/Services/` and are the preferred location for business logic. They receive DTOs, use DB transactions, fire domain events, and are injected via the container.

```php
// Calling a service
app(EventService::class)->publish($event);

// Or via facade
Finance::cancel($order);
```

The `Actions/` directories contain legacy `lorisleiva/laravel-actions` classes. Some are still in use, but new business logic goes in Services. Don't create new Action classes using the laravel-actions pattern.

### Cross-module communication

Modules fire domain events; the integration layer (or other modules) listens. Finance fires `OrderSettled`, `OrderCancelled`, `OrderRefunded`, `TransactionCleared`. Events fires `EventScheduling`. SpaceManagement listens to validate conflicts.

Finance and SpaceManagement are fully decoupled — neither references the other's internal state model. Finance is a payment processor, not a space-product-aware system.

### Filament panels

Three panels in `app/Filament/`:

- **Staff** (`/Staff/`) — admin panel for managing reservations, events, orders, equipment, users, site content
- **Member** (`/Member/`) — member-facing features (reservations, bands, profiles)
- **Band** (`/Band/`) — band management with Filament tenancy (each band is a tenant)

Plus a **Support** panel for help ticket management.

### Filament resource organization

Resources use organized subdirectories:

```
app/Filament/Staff/Resources/Orders/
├── OrderResource.php       # Resource class — delegates to form/table configs
├── Actions/                # Filament UI actions (CancelOrderAction, RefundOrderAction, etc.)
├── Pages/                  # CRUD pages (ListOrders, ViewOrder)
└── RelationManagers/       # Related data tables
```

Some resources also have `Schemas/` and `Tables/` subdirectories for form/table configuration classes.

### Filament action pattern

Filament actions are extracted into reusable classes with a static `make()` method. They live in the resource's `Actions/` subdirectory or in `app/Filament/Shared/Actions/` when shared across panels.

```php
class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->visible(fn (Order $record) => $record->status instanceof Pending)
            ->action(function (Order $record) {
                Finance::cancel($record);
            });
    }
}
```

### State machines (spatie/model-states)

States live in `{module}/src/States/{ModelName}/`. Each state is its own class extending a base state class. The base state class registers allowed transitions.

Current state machines:
- **OrderState**: Pending → Completed / Comped / Cancelled; Completed / Comped → Refunded
- **TransactionState**: Pending → Cleared / Failed / Cancelled
- **ReservationState**: Scheduled → Confirmed, Reserved → Confirmed, Confirmed → Completed (Scheduled/Reserved/Confirmed can all → Cancelled)
- **EquipmentState**: Available / Loaned / Maintenance
- **EquipmentLoanState**: Requested → ReadyForPickup → CheckedOut → DropoffScheduled → Returned (plus Overdue, DamageReported, Cancelled, StaffPreparing, StaffProcessingReturn)
- **TicketState**: Pending → Valid → CheckedIn (plus Cancelled)

## Critical rules

### Money

All monetary values are stored as integers in cents. `1500` = $15.00. Models cast money columns with `MoneyCast::class . ':USD'`. Never store decimals. The `finller/laravel-money` package provides Money value objects.

### Timezone

App timezone is `America/Los_Angeles`. Database stores UTC and converts automatically via model casts. Always use `now()` / `today()` helpers. When parsing date strings, specify timezone: `Carbon::parse($date, config('app.timezone'))`.

### Transaction sign convention

From the organization's perspective: payments are positive, refunds are negative.

### Purchasable lock

Any Purchasable (Reservation, Ticket, EquipmentLoan) is immutable while an active Order is attached. The only reshape path is cancel-and-rebook. Models using the `Purchasable` trait declare which fields are safe to modify via `getLockableFields()`.

### Order commitment

No auto-commit. Every Pending → Cleared transition is user-initiated (or webhook-driven for payments already committed). No scheduled or background job commits Orders.

### Order cohesion

Orders are cohesive commitments, not baskets of independent items. Line items are interdependent, justifying Order-level all-or-nothing cancellation/refund behavior.

### Credit eligibility

Which wallets can discount a product is declared by each Product subclass via `getEligibleWallets()`, not by a reverse applicable_to list in config.

### Reservation confirmation

Orders are created at confirmation time (not booking time). `ConfirmReservationAction` handles Scheduled reservations. Pay buttons on Confirmed reservations are retry-only.

## Testing

Pest-based. All tests extend `Tests\TestCase` which uses `RefreshDatabase`, seeds settings (reservation buffer, equipment flags) and roles (via `PermissionSeeder`).

```php
// Pest syntax
it('can cancel a pending order', function () {
    $order = Order::factory()->create();
    Finance::cancel($order);
    expect($order->fresh()->status)->toBeInstanceOf(Cancelled::class);
});
```

Module tests live in `app-modules/*/tests/`. Integration and policy tests live in `tests/Feature/`. Both are configured in `Pest.php` to use the same base TestCase.

Tests run in parallel: `composer test`.

## Policies and authorization

Policies live in `app/Policies/` (integration layer, never in modules). Models expose boolean helpers (`isOrganizedBy()`, `isOwnedBy()`) that policies call. Role-based access uses `spatie/laravel-permission`.

## Key packages

- `spatie/laravel-permission` — roles and permissions
- `spatie/laravel-model-states` — state machines
- `spatie/laravel-data` — DTOs
- `spatie/laravel-medialibrary` — file uploads (via Filament plugin)
- `spatie/laravel-tags` — tagging
- `spatie/laravel-activitylog` — audit logging
- `spatie/period` — time period conflict detection
- `watson/validating` — model validation rules
- `rlanvin/php-rrule` — recurring reservation rules

## Style

Run `vendor/bin/pint` before committing. No co-author lines in git commits.
