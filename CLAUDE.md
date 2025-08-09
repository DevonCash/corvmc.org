# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based web application for the Corvallis Music Collective, a nonprofit organization.

## Technologies

### Core Framework Stack

- **Laravel 12** - PHP framework
- **Filament PHP v4** - Admin panel and forms framework
- **SQLite** - Database (development)
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
- **unicon-rocks/unicon-laravel** - Frontent icon system

### Frontend Integration

- TailwindCSS v4 with Vite plugin
- Laravel Vite plugin for asset compilation
- Filament handles most UI components and styling
- Custom CSS in `resources/css/app.css`

## Development Commands

### Server and Development

- `composer dev` - Start development environment (runs server, queue, logs, and vite concurrently)
- `php artisan serve` - Start Laravel development server
- `npm run dev` - Start Vite development server for assets
- `npm run build` - Build production assets

### Testing and Quality

- `composer test` - Run the test suite (clears config and runs PHPUnit/Pest tests)
- `vendor/bin/pest` - Run Pest tests directly
- `vendor/bin/pint` - Laravel Pint code style fixer

### Database

- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeders
- `php artisan db:seed` - Run database seeders

### Queue and Background Jobs

- `php artisan queue:work` - Start queue worker
- `php artisan queue:listen --tries=1` - Listen for queue jobs with single try

### Logs and Debugging

- `php artisan pail --timeout=0` - Real-time log viewer

## Architecture Overview

### Third-Party Integrations

- **Zeffy** - Payment processing via Zapier webhooks for donations and memberships
- **Filament Plugins** - spatie/laravel-medialibrary-plugin, spatie/laravel-tags-plugin

### Application Structure

#### Models and Domain Logic

Core models include:

- `User` - Authentication and base user data
- `MemberProfile` - Extended user profiles with bio, links, skills (tags), and media
- `BandProfile` - Band information and social media links
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

### Database Design

- Uses descriptive migration timestamps (2025_07_30_*)
- Implements soft deletes and proper foreign key relationships
- Media library integration for file attachments
- Permission system tables for role-based access

## Development Guidelines

### Model Conventions

- Use Spatie traits: `HasTags`, `InteractsWithMedia`, `HasRoles` as appropriate
- Implement proper accessor methods for computed properties (e.g., `getAvatarAttribute`)
- Define clear fillable arrays and relationships
- Add descriptive docblocks for model purpose

### Filament Resource Patterns

- Separate form schemas, infolists, and table configurations into dedicated classes
- Use proper page routing in `getPages()` method
- Leverage Filament's built-in widgets and components
- Follow the established directory structure for consistency

### Testing

- Tests use in-memory SQLite database
- Both Feature and Unit test suites configured
- Use Pest testing framework syntax
- Environment properly isolated for testing
