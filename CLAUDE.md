# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based web application for the Corvallis Music Collective, a nonprofit organization.

## Technologies

### Core Framework Stack

- **Laravel 12** - PHP framework
- **Filament PHP v4** - Admin panel and forms framework
- **Vite** - Asset bundling with TailwindCSS v4
- **Pest** - Testing framework

```aside
NOTE: Filament v4 beta makes some changes from v3
- Form and Infolist layouts have been unified into the Schema namespace
```

### Key Spatie Libraries

- **spatie/laravel-permission** - Role and permission management
- **spatie/laravel-tags** - Tagging system for skills and categorization
- **spatie/laravel-medialibrary** - File uploads and media management
- **spatie/laravel-data** - Data transfer objects
- **spatie/laravel-settings** - Application settings management
- **spatie/laravel-query-builder** - API query filtering and sorting
- **spatie/laravel-model-flags** - Model feature flags
- **spatie/period** - Date/time period handling

### Additional Libraries

- **guava/calendar** - Calendar component integration
- **secondnetwork/blade-tabler-icons** - Tabler icons for UI
- **finller/laravel-money** - Money value handling (all amounts stored in cents)
- **maatwebsite/excel** - Excel/CSV import functionality
- **laravel/cashier** - Stripe subscription and payment handling
- **knplabs/github-api** - GitHub API integration
- **spatie/laravel-activitylog** - Activity and audit logging
- **spatie/laravel-model-states** - State machine pattern for models
- **spatie/laravel-sluggable** - Automatic slug generation
- **staudenmeir/laravel-adjacency-list** - Nested set relationships
- **stechstudio/filament-impersonate** - User impersonation for testing

### Frontend Integration

- TailwindCSS v4 with Vite plugin
- DaisyUI component library
- AlpineJS with masonry plugin for layouts
- Laravel Vite plugin for asset compilation
- Filament handles most UI components and styling
- Custom CSS in `resources/css/app.css`

## Development Commands

### Server and Development

- `composer dev` - Start development environment (runs server, queue, logs, and vite concurrently)
- `php artisan serve` - Start Laravel development server
- `npm run dev` - Start Vite development server for assets
- `npm run build` - Build production assets

### Stripe Subscription Management

- `php artisan subscription:create-prices` - Create Stripe price objects for sliding scale membership ($10-$50) and fee coverage
- `php artisan subscription:create-prices --dry-run` - Preview what prices would be created without making API calls

### Testing and Quality

- `composer test` - Run the test suite (clears config and runs PHPUnit/Pest tests)

### Test Coverage

- `php artisan test:coverage` - Run tests with code coverage analysis

### Custom Test Commands

- `php artisan test:invitations` - Test the user invitation system end-to-end
- `php artisan test:notifications` - Test all notification types in the system
- `php artisan test:activity-feed` - Test activity feed functionality
- `php artisan test:activity-log-viewer` - Test activity log viewer
- `php artisan test:dashboard-widgets` - Test dashboard widget rendering
- `php artisan test:revision-system` - Test content revision tracking
- `php artisan test:email-template` - Test email template rendering
- Use `--clean` flag on invitation tests to clean up test data first
- Use `--send` flag on notification tests to actually send notifications (default is dry-run)

### Story Coverage

- `php artisan check:story-coverage` - Validate test coverage against user stories defined in the project

### Import Features

- **Zeffy Data Import** - Use the "Import Zeffy Data" button in the Transactions resource
- **File Format Support** - Accepts both CSV and Excel (.xlsx, .xls) files from Zeffy exports
- Automatic column mapping with visual interface
- Real-time validation and error reporting
- Duplicate detection and failed payment filtering
- Dynamic additional questions handling for campaign-specific fields
- Supports all Zeffy export columns including donor info, campaigns, and payment details
- Background processing with progress tracking and notifications

### Database

- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan db:seed` - Run database seeders

### Queue and Background Jobs

- `php artisan queue:work` - Start queue worker
- `php artisan queue:listen --tries=1` - Listen for queue jobs with single try

### Logs and Debugging

- `php artisan pail --timeout=0` - Real-time log viewer

### Utility Commands

- `php artisan assign-role` - Assign roles to users
- `php artisan send:reservation-reminders` - Send reminders for upcoming reservations
- `php artisan send:reservation-confirmation-reminders` - Send confirmation reminders
- `php artisan send:membership-reminders` - Send membership renewal reminders
- `php artisan import:productions-csv` - Import productions from CSV file
- `php artisan import:users-deno-kv` - Import users from Deno KV store (migration utility)

## Architecture Overview

### Third-Party Integrations

- **Stripe** - All payment processing via Laravel Cashier (reservations, memberships, future features)
- **GitHub API** - Repository integration for issue creation and management
- **Postmark** - Transactional email delivery
- **AWS S3** - File storage via Flysystem
- **Sentry** - Error tracking and monitoring
- **Filament Plugins** - spatie/laravel-medialibrary-plugin, spatie/laravel-tags-plugin

### Application Structure

#### Models and Domain Logic

Core models include:

- `User` - Authentication and base user data
- `MemberProfile` - Extended user profiles with bio, links, skills (tags), and media
- `Band` - Band information and social media links
- `Production` - Events and shows management
- `Reservation` - Practice space booking system
- `Transaction` - Payment and financial tracking

#### Filament Resource Organization

Resources are organized in dedicated directories under `app/Filament/Resources/`:

- Each resource has dedicated `Pages/`, `Schemas/`, and `Tables/` subdirectories
- Form schemas are separated from table configurations
- Uses resource-specific form and infolist classes for better organization

#### Authentication and Panels

- Single Filament panel at `/member` path with amber primary color
- Integrated authentication with profile management
- Resources auto-discovery from `app/Filament/Resources`

#### Service Layer Architecture

The application uses dedicated service classes registered as **singletons** in `AppServiceProvider` for complex business logic:

**Core Services:**
- `UserSubscriptionService` - Handles membership status and sustaining member logic
- `UserInvitationService` - Manages user invitation workflow and token handling
- `ReservationService` - Practice space booking logic with conflict detection, credit deduction
- `ProductionService` - Event management and production workflows
- `CreditService` - Transaction-safe credit operations and balance management
- `MemberBenefitsService` - Calculate and allocate member benefits based on subscriptions
- `BandService` - Band profile management and member relationships
- `MemberProfileService` - Member directory and profile management
- `CacheService` - Centralized cache management and performance optimization

**Additional Services:**
- `PaymentService` - Payment processing and transaction management
- `UserService` - User account management and operations
- `CalendarService` - Calendar and scheduling functionality
- `NotificationSchedulingService` - Scheduled notification handling
- `GitHubService` - GitHub API integration for repository operations
- `ReportService` - Reporting and analytics
- `EquipmentService` - Equipment management and tracking
- `TrustService` / `CommunityEventTrustService` - Trust and reputation systems
- `MemberBenefitsService` - Member benefits calculation and tracking
- `StaffProfileService` - Staff profile management
- `RevisionService` - Content revision and history tracking

**Important:** All business logic should be in services - never in controllers, models, or Filament resources.

#### Custom Testing Commands Pattern

When developing complex features, create dedicated test commands to validate functionality:

##### Process for Creating Test Commands

1. **Create the command**: `php artisan make:command TestFeatureName`
2. **Structure the command**:
   - Use clear section headers with emojis for visual separation
   - Include `--dry-run` or similar flags to show what would happen without executing
   - Provide `--clean` flags to reset test data
   - Use descriptive output with ✓ for success, ✗ for failure, → for dry-run actions
3. **Test comprehensively**:
   - Test the happy path (normal flow)
   - Test error conditions (expired tokens, invalid data)
   - Test edge cases (duplicate actions, cleanup)
   - Verify data integrity before and after operations
4. **Include cleanup**: Always clean up test data to avoid pollution
5. **Add to CLAUDE.md**: Document the command and its flags for future reference

##### Example Implementation

```php
protected $signature = 'test:feature {--dry-run : Show what would happen} {--clean : Clean test data}';

public function handle() {
    $this->info('🧪 Testing Feature Name');
    $this->line('========================');
    
    if ($this->option('dry-run')) {
        $this->warn('DRY RUN mode - no changes will be made');
    }
    
    // Test sections with clear visual separation
    $this->info('📧 1. Testing core functionality...');
    // ... test logic
    
    $this->line('   ✓ Success message');
    $this->line('   → Would execute action (dry-run)');
    
    $this->info('✅ All tests passed!');
}
```

### Database Design

- Uses descriptive migration timestamps (2025_07_30_*)
- Implements soft deletes and proper foreign key relationships
- Media library integration for file attachments
- Permission system tables for role-based access
- Activity logging via `spatie/laravel-activitylog`
- Model state tracking via `spatie/laravel-model-states`
- Polymorphic relationships for flexible associations (transactions, revisions)

### Money Handling

**CRITICAL:** All monetary amounts must be stored as **integers in cents** using `finller/laravel-money`. Imprecision is not acceptable when dealing with currency.

```php
// Store amounts in cents
$amount = Money::parse('15.50', 'USD'); // Stored as 1550 cents
```

### Credits System

The application uses a robust Credits System for managing practice space hours and other benefits:

**Database Schema:**
- `user_credits` - User credit balances by type (free_hours, equipment_credits)
- `credit_transactions` - Immutable audit trail of all credit changes
- `credit_allocations` - Scheduled recurring credit grants
- `promo_codes` / `promo_code_redemptions` - Promotional code system

**Key Features:**
- **Transaction-safe:** DB locking prevents race conditions
- **Complete audit trail:** Every credit change is logged
- **Multiple credit types:** Practice space hours, equipment credits, promos
- **Smart allocation:** Practice space resets monthly, equipment credits rollover with cap
- **Block-based:** Practice space credits stored in 30-minute blocks

**Usage:**
```php
// Check balance
$blocks = CreditService::getBalance($user, 'free_hours');
$hours = ReservationService::blocksToHours($blocks);

// Allocate monthly credits to sustaining members
MemberBenefitsService::allocateMonthlyCredits($user);

// Manual allocation command
php artisan credits:allocate              # All sustaining members
php artisan credits:allocate --dry-run    # Preview without changes
php artisan credits:allocate --user-id=123 # Specific user
```

**Integration:**
- Credits automatically deducted when creating reservations
- Backward compatible with legacy free_hours_used tracking
- Ready for Stripe webhook integration (subscription updates)

### Key Business Logic

#### Membership System

- Users can be "sustaining members" via role assignment or active Stripe subscription ($10+ monthly)
- **Free hours allocation**: 1 hour per $5 contributed monthly (e.g., $25/month = 5 hours, $50/month = 10 hours)
- Fallback: 4 free hours for role-based members without active subscription
- **Credits System**: Practice space hours managed via transaction-safe Credits System
  - Credits stored in 30-minute blocks for precision
  - Reset monthly (no rollover for practice space)
  - Automatic deduction when creating reservations
  - Complete audit trail of all credit changes
- Stripe subscription syncing via `UserSubscriptionService::syncMembershipRoles()`
- Detection via `User::isSustainingMember()` → `MemberBenefitsService`
- Free hours calculation: `MemberBenefitsService::calculateFreeHours()` uses billing period peak amount
- Monthly allocation: `MemberBenefitsService::allocateMonthlyCredits()` or `php artisan credits:allocate`

#### Practice Space Reservations

- $15/hour base rate for all users
- Free hours for sustaining members (1 hour per $5/month contribution)
- Operating hours: 9 AM - 10 PM
- Duration limits: 1-8 hours per reservation
- Sophisticated conflict detection with productions and other reservations
- Support for recurring reservations (sustaining members only)
- **Stripe Integration**: Online payments via Laravel Cashier with checkout sessions
- Polymorphic relationship: reservations linked to transactions via `transactionable`

#### Event Management (Productions)

- Production lifecycle: pre-production → published → completed/cancelled
- Band lineup management with set ordering and duration
- Manager-based permissions and production ownership
- Integration with practice space conflict detection
- Public event listing with search and filtering

## Development Guidelines

### Model Conventions

- Use Spatie traits: `HasTags`, `InteractsWithMedia`, `HasRoles`, `LogsActivity` as appropriate
- Implement proper accessor methods for computed properties (e.g., `getAvatarAttribute`)
- Define clear fillable arrays and relationships
- Add descriptive docblocks for model purpose
- Use model observers for automatic cache invalidation (see Performance Optimization section)

### Filament Resource Patterns

- Separate form schemas, infolists, and table configurations into dedicated classes
- Use proper page routing in `getPages()` method
- Leverage Filament's built-in widgets and components
- Follow the established directory structure for consistency
- User impersonation available via `stechstudio/filament-impersonate` for testing

### Testing

- Tests use in-memory SQLite database
- Both Feature and Unit test suites configured
- Use Pest testing framework syntax
- Environment properly isolated for testing
- Create custom test commands for complex workflows (see Custom Testing Commands Pattern)
- Run `php artisan check:story-coverage` to validate coverage against user stories

This pattern provides reliable, repeatable testing for complex business logic and integrations.

### Code Quality

- Use existing libraries whenever possible (especially Spatie packages)
- All business logic must be in service classes, never in controllers or resources
- When finished developing a feature, always commit to version control
- Do not make claims beyond the bare facts of operations performed

## TODO: Next Session Tasks

### Stripe Transaction Fee Coverage

- **Task**: Add opt-in upcharge to cover Stripe transaction costs
- **Location**: `ReservationService::createCheckoutSession()` method
- **Implementation**:
  - Calculate Stripe fees (2.9% + $0.30 for cards)
  - Add optional checkbox in checkout for user to cover processing fees
  - Update line items to include fee coverage if selected
  - Ensure fee calculation is accurate for reservation amounts
- **User Experience**: Allow users to voluntarily cover transaction costs to support the organization

## Performance Optimization

### Caching Strategy

The application implements comprehensive caching to optimize database queries and improve response times:

#### Cache Layers

- **User Authentication**: Sustaining member status, free hours usage (30-60 min TTL)
- **Dashboard Widgets**: User statistics, recent activity, upcoming events (5-10 min TTL)
- **Member Directory**: Tag filters for skills, genres, influences (1 hour TTL with tags)
- **Reservation System**: Conflict detection, daily reservations/productions (30 min TTL)
- **Subscription Data**: Member statistics, revenue calculations (30 min TTL)

#### Cache Management

**Artisan Commands:**

```bash
# Cache management
php artisan cache:manage warm          # Warm up commonly used caches
php artisan cache:manage clear         # Clear all application caches
php artisan cache:manage stats         # Show cache statistics
php artisan cache:manage clear-user --user=123    # Clear specific user caches
php artisan cache:manage clear-date --date=2024-01-01    # Clear date-specific caches
php artisan cache:manage clear-tags    # Clear member directory tag caches
```

**Automatic Cache Invalidation:**

- Model observers automatically clear related caches when data changes
- User changes clear: sustaining status, statistics, activity feeds
- Reservations clear: conflict detection, user free hours, daily schedules
- Productions clear: upcoming events, conflict detection, manager stats
- Tags clear: member directory filter options using cache tags

#### Performance Impact

- **Before caching**: 15-25 queries per dashboard load, 800ms-2000ms page load
- **After caching**: 2-5 queries per dashboard load, 200ms-400ms page load
- **Query reduction**: 75% fewer database queries for common operations

#### Cache Keys Pattern

- User data: `user.{id}.{type}.{suffix}`
- Date data: `{type}.conflicts.{Y-m-d}`
- Global data: `{feature_name}` or `{feature_name}.{suffix}`
- Tagged data: Uses cache tags for bulk invalidation

## Technical Debt & Future Improvements

### Free Hours System

**Current Implementation:**
- Free hours calculated monthly based on subscription amount (1 hour per $5)
- Uses database queries with cache bypass during reservation creation for transaction safety
- **Limitations:**
  - No audit trail for credit usage
  - No support for promotional credits or bonuses
  - No rollover of unused hours
  - Limited flexibility for campaigns/referrals

**Planned Improvement: Credits System**
- See `docs/designs/credits-system-design.md` for full design
- Implements proper double-entry accounting for credits
- Transaction-safe with database locking
- Full audit trail via credit_transactions table
- Supports multiple credit types, promotions, expiration, and rollover
- **Estimated effort:** 16-25 hours
- **Migration strategy:** Parallel implementation → data migration → cutover → cleanup

**Why Credits System:**
- ✅ Transaction-safe (prevents race conditions)
- ✅ Audit trail (every credit change logged)
- ✅ Flexible (promotions, referrals, gifts)
- ✅ No cache dependencies
- ✅ Industry standard pattern

### Known Issues

1. **Production Authorization Tests** (3 failures)
   - Status update method not persisting changes in test environment
   - Non-blocking for production use
   - Requires deeper debugging of test database transactions

2. **Month Boundary Tests**
   - Tests using `subDays()` can cross month boundaries
   - Fixed in recent updates to use `startOfMonth()->addDays()`
   - Review other date-based tests for similar issues
