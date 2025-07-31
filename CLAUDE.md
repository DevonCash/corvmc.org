# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based web application for the Corvallis Music Collective, a nonprofit organization. The application is built using Laravel 12, Filament PHP for the admin interface, and integrates several Spatie packages for enhanced functionality.

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

### Core Framework Stack
- **Laravel 12** - PHP framework
- **Filament PHP v4** - Admin panel and forms framework
- **SQLite** - Database (development)
- **Vite** - Asset bundling with TailwindCSS v4
- **Pest** - Testing framework

### Key Spatie Packages Integration
- **spatie/laravel-permission** - Role and permission management
- **spatie/laravel-tags** - Tagging system for skills and categorization
- **spatie/laravel-medialibrary** - File uploads and media management
- **spatie/laravel-data** - Data transfer objects

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

### Frontend Integration
- TailwindCSS v4 with Vite plugin
- Laravel Vite plugin for asset compilation
- Filament handles most UI components and styling
- Custom CSS in `resources/css/app.css`

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