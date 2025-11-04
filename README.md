# Corvallis Music Collective

A Laravel-based web application for managing a nonprofit music collective in Corvallis, Oregon.

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- PostgreSQL (production) or SQLite (development)

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd corvmc-redux

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run build
```

### Development

**Start all development services:**
```bash
composer dev
```

This runs server, queue worker, logs viewer, and Vite concurrently.

**Individual services:**
```bash
php artisan serve          # Development server
php artisan queue:work     # Queue worker
php artisan pail           # Log viewer
npm run dev                # Vite dev server with hot reload
```

### Testing

```bash
composer test              # Run full test suite
composer test:coverage     # Run with coverage report
php artisan test --filter=TestName  # Run specific test
```

## Tech Stack

- **Laravel 12** - PHP framework
- **Filament v4** - Admin panel and forms
- **Livewire 3** - Dynamic UI components
- **Vite + Tailwind CSS v4** - Asset bundling and styling
- **Pest** - Testing framework
- **PostgreSQL** - Production database
- **Stripe** (Laravel Cashier) - Payment processing

### Key Packages

- `lorisleiva/laravel-actions` - Action-based architecture
- `spatie/laravel-permission` - Role-based access control
- `spatie/laravel-tags` - Tagging system
- `spatie/laravel-medialibrary` - File uploads
- `spatie/period` - Time conflict detection
- `guava/calendar` - Calendar widgets

## Application Architecture

### Action-Based Business Logic

Business logic lives in Actions (`app/Actions/`) organized by domain:

```
app/Actions/
├── Bands/           # Band management
├── Credits/         # Credit system for free hours
├── MemberProfiles/  # Member directory
├── Payments/        # Payment calculations
├── Productions/     # Event management
├── Reservations/    # Practice space booking
└── ...
```

**Usage:**
```php
// Call actions directly
$reservation = CreateReservation::run($user, $startTime, $endTime);

// Or dispatch to queue
CreateReservation::dispatch($user, $startTime, $endTime);
```

### Filament Resources

Admin interface resources in `app/Filament/Resources/`:

```
app/Filament/Resources/
├── ModelResource.php
└── ModelResource/
    ├── Pages/      # CRUD pages
    ├── Schemas/    # Form/infolist schemas
    └── Tables/     # Table configurations
```

### Public Routes

```
/                   # Homepage
/events             # Event listing
/members            # Member directory
/bands              # Band directory
/contribute         # Donation page
/member             # Member panel (Filament)
```

## Key Features

### Membership System
- User registration and authentication
- Role-based permissions (member, sustaining member, staff, admin)
- Manual sustaining member assignment

### Practice Space Reservations
- $15/hour with 4 free hours/month for sustaining members
- Conflict detection with events
- Recurring reservations
- Multiple payment methods

### Event Management (Productions)
- Event creation and publishing
- Band lineup management
- Poster uploads
- Public event listings
- Genre tagging

### Member & Band Directories
- Searchable public directories
- Rich profiles with skills and genres
- Visibility controls
- Avatar management
- Band invitation system

## Database

### Money Handling
**CRITICAL**: All monetary values stored as integers in cents.

```php
// $15.00 is stored as 1500
protected function casts(): array {
    return [
        'cost' => MoneyCast::class . ':USD',
    ];
}
```

### Timezone Handling
- **App Timezone**: `America/Los_Angeles` (PST/PDT)
- **Database**: Stores in UTC, converts automatically via model casts
- Use `now()` and `today()` helpers
- Always specify timezone when parsing strings: `Carbon::parse($date, config('app.timezone'))`

## Configuration

### Environment Variables

```env
APP_TIMEZONE=America/Los_Angeles

# Database
DB_CONNECTION=pgsql

# Stripe
STRIPE_KEY=
STRIPE_SECRET=

# Mail
MAIL_MAILER=postmark
POSTMARK_TOKEN=

# AWS S3 (for media storage)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

### Important Config Files

- `config/reservation.php` - Practice space business rules
- `config/permission.php` - Role/permission settings
- `config/media-library.php` - Media upload settings

## Development Workflow

### Adding a New Feature

1. Create Actions in `app/Actions/NewDomain/`
2. Create models with appropriate Spatie traits
3. Create Filament resource with organized subdirectories
4. Add public routes if needed
5. Write tests covering main workflows

### Code Style

Run Laravel Pint before committing:

```bash
vendor/bin/pint
```

### Common Tasks

```bash
# Create a new Action
php artisan make:action Domains/ActionName

# Create a Filament resource
php artisan make:filament-resource ModelName

# Create a migration
php artisan make:migration create_table_name

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear caches
php artisan optimize:clear
```

## Documentation

- **[CLAUDE.md](CLAUDE.md)** - Comprehensive AI assistant guidance and coding standards
- **[docs/ROADMAP.md](docs/ROADMAP.md)** - Planned features and enhancements

## License

MIT
