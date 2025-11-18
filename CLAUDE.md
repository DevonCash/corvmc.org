# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Laravel 12 application for the Corvallis Music Collective, a nonprofit organization operating out of Corvallis, Oregon. The codebase uses **Filament PHP v4** for admin interfaces and follows an **Action-based architecture** using `lorisleiva/laravel-actions` instead of traditional services.

## Core Technology Stack

- **Laravel 12** with **Filament v4** admin panels
- **Action-based architecture**: `lorisleiva/laravel-actions` for business logic
- **Spatie ecosystem**: permissions, tags, media library, model flags, periods, data DTOs
- **Pest testing** with SQLite in-memory database
- **Vite + TailwindCSS v4** with DaisyUI components
- **Stripe** (Laravel Cashier for reservation payments)

## Development Commands

### Running the Development Environment
```bash
composer dev  # Runs server, queue, logs, and vite concurrently
```

### Testing
```bash
composer test              # Run parallel test suite
composer test:coverage     # Run tests with Xdebug coverage
vendor/bin/pest            # Run single test file
vendor/bin/pest --filter TestName  # Run specific test
```

### Building Assets
```bash
npm run dev    # Development server with hot reload
npm run build  # Production build
```

### Common Artisan Commands
```bash
php artisan migrate        # Run migrations
php artisan db:seed        # Seed database
php artisan queue:work     # Process queue jobs
php artisan filament:upgrade  # Upgrade Filament resources
```

## Architecture & Patterns

### Action-Based Business Logic

**All business logic is implemented using Actions** (`lorisleiva/laravel-actions`), not traditional services or controllers. Actions are located in `app/Actions/` organized by domain:

```
app/Actions/
├── Bands/                 # Band management, member invitations
├── Cache/                 # Cache warming and invalidation
├── Credits/               # Credit system for free hours
├── Equipment/             # Equipment loans and management
├── MemberProfiles/        # Member directory and profiles
├── Payments/              # Payment calculations and processing
├── Productions/           # Event/show management
├── Reservations/          # Practice space booking and conflicts
└── [other domains]/
```

**Key Action patterns:**
- Use `AsAction` trait
- Return strongly-typed results
- Keep Actions focused on single responsibility
- Can be called as: `ActionName::run($params)` or `ActionName::dispatch($params)` (for queued)

**Example Action usage:**
```php
// In controllers, Filament resources, or other Actions
$reservation = CreateReservation::run($user, $startTime, $endTime);
$cost = CalculateReservationCost::run($user, $startTime, $endTime);
```

### Filament Resource Organization

Filament resources follow a strict directory structure:

```
app/Filament/Resources/
├── ModelResource.php           # Main resource class
└── ModelResource/
    ├── Pages/                  # CRUD pages
    ├── Schemas/               # Form/infolist schemas
    └── Tables/                # Table configurations
```

**Filament Panels:**
- Member panel at `/member` (amber theme)
- Admin functions integrated into member panel with role-based access
- Resources auto-discovered from `app/Filament/Resources/`

### Filament Actions in Livewire Components

When embedding Filament actions in Livewire components (Pages, Widgets, etc.), follow these requirements:

**Required traits and interfaces:**
```php
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class MyPage extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
```

**Action method requirements:**
- Method name must match the action name or action name + "Action"
- **CRITICAL: Must have explicit `: Action` return type** (without it, Livewire cannot find the action)
- Action name in `Action::make()` must match the method name

```php
// ✅ CORRECT - explicit return type
public function modifyMembershipAmountAction(): Action
{
    return ModifyMembershipAmountAction::make();
}

// ❌ WRONG - missing return type causes "Property not found" error
public function modifyMembershipAmountAction()
{
    return ModifyMembershipAmountAction::make();
}
```

**Rendering in Blade:**
```blade
{{ $this->modifyMembershipAmountAction }}
```

**Note:** `<x-filament-actions::modals />` is already included in `<x-filament-panels::page>`.

### Model Architecture

**Key models with important relationships:**
- `User` → `MemberProfile` (one-to-one extended profiles)
- `Band` → `BandMember` (many-to-many with roles: owner, admin, member)
- `Production` → polymorphic lineup with `Band` performers
- `Reservation` (base class) → STI with `RehearsalReservation` and `ProductionReservation`
- `Reservation` → polymorphic `reserver` (User, Production, Band, etc.)

**Common Spatie traits:**
```php
use HasRoles;           // Role-based permissions
use HasTags;            // Genre/skill tagging
use InteractsWithMedia; // Avatar/media uploads
use HasFlags;           // Feature flags (e.g., is_teacher, is_professional)
```

### Data Transfer Objects

The codebase uses `spatie/laravel-data` DTOs for complex data structures:
- `VenueLocationData` - Production venue information
- Cost calculation responses from Payment Actions

### Single Table Inheritance (STI)

The `Reservation` model uses STI with a `type` column:
- Base class: `Reservation`
- Subclasses: `RehearsalReservation`, `ProductionReservation`
- Automatic type assignment on creation

## Business Logic Domains

### Membership System
- **Roles**: member, sustaining member, staff, admin (via `spatie/laravel-permission`)
- **Sustaining members**: Manually assigned role by administrators
- **Benefits**: 4 free practice hours/month with credits system

### Practice Space Reservations
- **Pricing**: $15/hour base rate
- **Free hours**: Tracked via credits system (`user_credits` table)
- **Business hours**: 9 AM - 10 PM
- **Duration**: 1-8 hour blocks
- **Conflict detection**: Uses `spatie/period` to check against other reservations and productions
- **Payment**: Stripe checkout with optional fee coverage
- **Recurring**: Sustaining members can create recurring reservations

### Productions (Events/Shows)
- **Lifecycle**: pre-production → published → completed/cancelled (via `spatie/laravel-model-states`)
- **Public routes**: `/events`, `/events/{production}`, `/show-tonight`
- **Conflict detection**: Automatically checks for practice space conflicts
- **Manager permissions**: Only production managers can edit their productions
- **Performers**: Many-to-many with `Band`, includes set order and duration

### Member Directory
- **Public directory**: `/members` route with search/filtering
- **Visibility**: public, members-only, or private profiles
- **Skills/genres**: Tagged via `spatie/laravel-tags`
- **Flags**: `is_teacher`, `is_professional` for directory filtering

### Band Profiles
- **Public directory**: `/bands` route
- **Member roles**: owner, admin, member (pivot table with roles)
- **Invitations**: Email-based invitation system for adding members
- **EPK**: Planned media management for Electronic Press Kits

### Equipment Management
- **Equipment loans**: Checkout/return system with condition tracking
- **Damage reports**: Incident reporting for equipment issues
- **Reservations**: Equipment can be reserved alongside practice space

## Database Conventions

### Money Handling
**CRITICAL**: All monetary values are stored as **integers in cents**:
```php
// $15.00 is stored as 1500
protected function casts(): array {
    return [
        'cost' => MoneyCast::class . ':USD',
    ];
}
```

### Timestamps
- All models use `created_at`, `updated_at`
- Many use soft deletes (`deleted_at`)
- Reservations use `reserved_at`, `reserved_until` for time slots

### Indexes
Performance indexes on:
- Reservation `reserved_at`, `reserved_until`, `status`
- Production `start_time`, `published_at`, `status`
- User `email` (unique)

### Time Handling
**App Timezone**: `America/Los_Angeles` (PST/PDT)  
**Database**: PostgreSQL stores timestamps in UTC internally  
**Model Casts**: Automatically convert to/from app timezone

**CRITICAL Best Practices:**

✅ **DO:**
- Trust model casts - `$record->reserved_at` is already a Carbon instance
- Check instance type before parsing:
  ```php
  $reservedAt = $data['reserved_at'] instanceof Carbon
      ? $data['reserved_at']
      : Carbon::parse($data['reserved_at'], config('app.timezone'));
  ```
- Use `now()` and `today()` helpers (automatically use app timezone)
- Specify timezone when creating from strings:
  ```php
  Carbon::parse('2024-10-11 14:00', config('app.timezone'));
  ```

❌ **DON'T:**
- Re-parse Carbon instances: `Carbon::parse($record->reserved_at)` is wrong
- Create datetimes without timezone: `Carbon::parse('2024-10-11 14:00')` uses UTC!
- Use `toDateString()` then re-parse (loses time and timezone info)

**Historical Context:**  
A timezone migration was run that added 8 hours to all existing timestamps when the app switched from UTC to `America/Los_Angeles`. All datetime model attributes are properly cast and handle PST/PDT automatically.

## Testing Patterns

### Test Organization
- **Feature tests**: End-to-end workflows in `tests/Feature/`
- **Unit tests**: Action and model tests in `tests/Unit/`
- Uses Pest with `RefreshDatabase` trait
- In-memory SQLite for fast test execution

### Writing Tests
```php
// Feature test example
it('creates a reservation with correct pricing', function () {
    $user = User::factory()->create();
    $start = now()->addDay()->setHour(10);
    $end = $start->copy()->addHours(2);

    $reservation = CreateReservation::run($user, $start, $end);

    expect($reservation->cost)->toBe(3000); // $30.00 in cents
});
```

## Public Routes Structure

```
/                      # Homepage with upcoming events
/events                # Event listing with search/filtering
/events/{production}   # Individual event detail page
/show-tonight          # Redirect to next/current show
/members               # Member directory
/members/{profile}     # Individual member profile
/bands                 # Band directory
/bands/{band}          # Individual band profile
/equipment             # Equipment catalog
/about                 # Staff and board information
/contribute            # Donation/support page
/sponsors              # Sponsor listing
```

## Configuration & Environment

### Key Environment Variables
- `STRIPE_KEY`, `STRIPE_SECRET` - Stripe payment processing
- `AWS_*` - S3 for media storage (via `spatie/laravel-medialibrary`)
- `MAIL_*` - PostMark for transactional emails
- Database: PostgreSQL in production, SQLite for testing

### Important Config Files
- `config/reservation.php` - Practice space business rules
- `config/permission.php` - Role/permission settings
- `config/media-library.php` - Media upload/processing settings

## Common Development Tasks

### Adding a New Feature Domain
1. Create Actions in `app/Actions/NewDomain/`
2. Create models with appropriate Spatie traits
3. Create Filament resource with Schemas/ and Tables/ subdirectories
4. Add routes in `routes/web.php` if public-facing
5. Write feature tests covering main workflows

### Modifying Business Logic
1. Locate relevant Action in `app/Actions/`
2. Update Action's `handle()` method
3. Update related tests
4. Run test suite to ensure no regressions

### Adding a New Filament Resource
1. Create resource: `php artisan make:filament-resource ModelName`
2. Organize into subdirectories: `Pages/`, `Schemas/`, `Tables/`
3. Extract form schema to `Schemas/ModelNameForm.php`
4. Extract table config to `Tables/ModelNameTable.php`
5. Add policies for permission checks

## Key Packages & Their Uses

- **filament/filament** - Admin panel framework
- **lorisleiva/laravel-actions** - Business logic organization
- **spatie/laravel-permission** - Role-based access control
- **spatie/laravel-tags** - Tagging system for genres/skills
- **spatie/laravel-medialibrary** - File uploads (avatars, posters, EPKs)
- **spatie/laravel-data** - DTOs and type-safe data objects
- **spatie/period** - Time period calculations for conflict detection
- **spatie/laravel-model-flags** - Boolean feature flags on models
- **laravel/cashier** - Stripe integration for reservations
- **pestphp/pest** - Testing framework
- **guava/calendar** - Calendar widget in Filament

## PHPStan Type Safety Patterns

This codebase uses **PHPStan level 2** for static analysis. Follow these principles to maintain type safety:

### Type Hierarchy (Best to Worst)

1. **✅ Specific model classes** - Use when you know the exact type
   ```php
   public function handle(User $user, Event $event): Report
   ```

2. **✅ Interfaces for polymorphic cases** - Define contracts for shared behavior
   ```php
   // Define interface
   interface Reportable {
       public function reports(): MorphMany;
       public function getContentCreator(): ?User;
   }

   // Use in Actions
   public function handle(Reportable $content): void
   ```

3. **✅ Type checks with instanceof** - When relationships return mixed types
   ```php
   if ($performer instanceof Band) {
       $pivot = $performer->pivot;
   }
   ```

4. **✅ @property annotations** - For dynamic properties PHPStan can't infer
   ```php
   /**
    * @property-read User|null $organizer
    * @property PaymentStatus $payment_status
    * @property \App\Enums\Visibility|null $visibility
    */
   ```

5. **✅ @var type hints** - For query results and complex expressions
   ```php
   /** @var \App\Models\Reservation|null $lastReservation */
   $lastReservation = $user->reservations()->latest()->first();
   ```

6. **❌ Generic Model types** - Avoid unless absolutely necessary
   ```php
   // Bad
   public function handle(Model $content)

   // Good - use interface or specific type
   public function handle(Reportable $content)
   ```

### Common Patterns

**Static Property Pattern for Polymorphic Configuration:**
```php
// In trait
protected static string $creatorForeignKey = 'user_id';

public function getContentCreator(): ?User {
    $foreignKey = static::$creatorForeignKey;
    $relationshipName = str_replace('_id', '', $foreignKey);
    return $this->{$relationshipName};
}

// In model - override when needed
protected static string $creatorForeignKey = 'organizer_id';
```

**Method Existence Checks for Trait Methods:**
```php
// When morphTo returns generic Model but you need trait methods
if (!method_exists($model, 'forceUpdate')) {
    throw new \InvalidArgumentException('Model does not support revisions');
}
$model->forceUpdate($data);
```

**Laravel Scope Methods:**
```php
// PHPStan doesn't understand Laravel's scope magic
/** @phpstan-ignore method.notFound */
return $query->public();
```

**Relationship Properties:**
```php
// Add to model class for relationships accessed as properties
/**
 * @property-read User $submittedBy
 * @property-read User|null $reviewedBy
 */
```

**Enum Casts:**
```php
// Always annotate enum properties
/**
 * @property PaymentStatus $payment_status
 * @property ReservationStatus $status
 */
class Reservation extends Model {
    protected function casts(): array {
        return [
            'payment_status' => PaymentStatus::class,
            'status' => ReservationStatus::class,
        ];
    }
}
```

**Pivot Attributes:**
```php
foreach ($event->performers as $performer) {
    if ($performer instanceof Band) {
        /** @var \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot */
        $pivot = $performer->pivot;
        $order = $pivot?->order;
    }
}
```

### When to Use Annotations vs Refactoring

**Use @property annotations when:**
- Property exists on child models but not in parent (trait context)
- Enum/cast properties that PHPStan doesn't automatically understand
- Relationship dynamic properties (`$user->profile`)

**Refactor instead of annotating when:**
- Using generic `Model` type - create interface or use specific type
- Method doesn't exist - use `method_exists()` check or create proper interface
- Accessing properties that don't exist - indicates architectural issue

**Example of good refactoring:**
```php
// Before (needed many @property annotations)
public function handle(Model $reportable) {
    $reportable->id; // PHPStan error
    $reportable->reports(); // PHPStan error
}

// After (interface defines contract)
public function handle(Reportable $reportable) {
    $reportable->id; // Works - interface has @property
    $reportable->reports(); // Works - interface defines method
}
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.10
- filament/filament (FILAMENT) - v4
- laravel/cashier (CASHIER) - v15
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== filament/core rules ===

## Filament
- Filament is used by this application, check how and where to follow existing application conventions.
- Filament is a Server-Driven UI (SDUI) framework for Laravel. It allows developers to define user interfaces in PHP using structured configuration objects. It is built on top of Livewire, Alpine.js, and Tailwind CSS.
- You can use the `search-docs` tool to get information from the official Filament documentation when needed. This is very useful for Artisan command arguments, specific code examples, testing functionality, relationship management, and ensuring you're following idiomatic practices.
- Utilize static `make()` methods for consistent component initialization.

### Artisan
- You must use the Filament specific Artisan commands to create new files or components for Filament. You can find these with the `list-artisan-commands` tool, or with `php artisan` and the `--help` option.
- Inspect the required options, always pass `--no-interaction`, and valid arguments for other options when applicable.

### Filament's Core Features
- Actions: Handle doing something within the application, often with a button or link. Actions encapsulate the UI, the interactive modal window, and the logic that should be executed when the modal window is submitted. They can be used anywhere in the UI and are commonly used to perform one-time actions like deleting a record, sending an email, or updating data in the database based on modal form input.
- Forms: Dynamic forms rendered within other features, such as resources, action modals, table filters, and more.
- Infolists: Read-only lists of data.
- Notifications: Flash notifications displayed to users within the application.
- Panels: The top-level container in Filament that can include all other features like pages, resources, forms, tables, notifications, actions, infolists, and widgets.
- Resources: Static classes that are used to build CRUD interfaces for Eloquent models. Typically live in `app/Filament/Resources`.
- Schemas: Represent components that define the structure and behavior of the UI, such as forms, tables, or lists.
- Tables: Interactive tables with filtering, sorting, pagination, and more.
- Widgets: Small component included within dashboards, often used for displaying data in charts, tables, or as a stat.

### Relationships
- Determine if you can use the `relationship()` method on form components when you need `options` for a select, checkbox, repeater, or when building a `Fieldset`:

<code-snippet name="Relationship example for Form Select" lang="php">
Forms\Components\Select::make('user_id')
    ->label('Author')
    ->relationship('author')
    ->required(),
</code-snippet>


## Testing
- It's important to test Filament functionality for user satisfaction.
- Ensure that you are authenticated to access the application within the test.
- Filament uses Livewire, so start assertions with `livewire()` or `Livewire::test()`.

### Example Tests

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1))
        ->searchTable($users->last()->email)
        ->assertCanSeeTableRecords($users->take(-1))
        ->assertCanNotSeeTableRecords($users->take($users->count() - 1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Howdy',
            'email' => 'howdy@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Howdy',
        'email' => 'howdy@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Multiple Panels (setup())" lang="php">
    use Filament\Facades\Filament;

    Filament::setCurrentPanel('app');
</code-snippet>

<code-snippet name="Calling an Action in a Test" lang="php">
    livewire(EditInvoice::class, [
        'invoice' => $invoice,
    ])->callAction('send');

    expect($invoice->refresh())->isSent()->toBeTrue();
</code-snippet>


=== filament/v4 rules ===

## Filament 4

### Important Version 4 Changes
- File visibility is now `private` by default.
- The `deferFilters` method from Filament v3 is now the default behavior in Filament v4, so users must click a button before the filters are applied to the table. To disable this behavior, you can use the `deferFilters(false)` method.
- The `Grid`, `Section`, and `Fieldset` layout components no longer span all columns by default.
- The `all` pagination page method is not available for tables by default.
- All action classes extend `Filament\Actions\Action`. No action classes exist in `Filament\Tables\Actions`.
- The `Form` & `Infolist` layout components have been moved to `Filament\Schemas\Components`, for example `Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.
- A new `Repeater` component for Forms has been added.
- Icons now use the `Filament\Support\Icons\Heroicon` Enum by default. Other options are available and documented.

### Organize Component Classes Structure
- Schema components: `Schemas/Components/`
- Table columns: `Tables/Columns/`
- Table filters: `Tables/Filters/`
- Actions: `Actions/`


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== livewire/core rules ===

## Livewire Core
- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices
- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

    ```blade
    @foreach ($items as $item)
        <div wire:key="item-{{ $item->id }}">
            {{ $item->name }}
        </div>
    @endforeach
    ```

- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
    public function mount(User $user) { $this->user = $user; }
    public function updatedSearch() { $this->resetPage(); }
</code-snippet>


## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->call('increment')
        ->assertSet('count', 1)
        ->assertSee(1)
        ->assertStatus(200);
</code-snippet>


    <code-snippet name="Testing a Livewire component exists within a page" lang="php">
        $this->get('/posts/create')
        ->assertSeeLivewire(CreatePost::class);
    </code-snippet>


=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2
- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
    - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
    - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
    - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
    - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives
- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine
- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks
- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
    Livewire.hook('request', ({ fail }) => {
        if (fail && fail.status === 419) {
            alert('Your session expired');
        }
    });

    Livewire.hook('message.failed', (message, component) => {
        console.error(message);
    });
});
</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest

### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest <name>`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
- Ask before adding phpstan-ignore annotations