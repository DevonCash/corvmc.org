# Corvallis Music Collective - System Documentation

This documentation covers the core systems and APIs for the Corvallis Music Collective web application, a Laravel-based platform for managing a nonprofit music collective.

## System Overview

The application is built using Laravel 12 with Filament PHP for the admin interface, and integrates several Spatie packages for enhanced functionality. It manages member profiles, band information, event productions, and practice space reservations.

## Core Systems

1. **[Member Profile System](member-profiles.md)** - User profiles with skills, visibility controls, and directory features
2. **[Band Profile System](band-profiles.md)** - Band management with member invitations and collaboration tools  
3. **[Production System](productions.md)** - Event and show management with ticketing and venue information
4. **[Reservation System](reservations.md)** - Practice space booking with pricing and availability management
5. **[User Subscription System](user-subscriptions.md)** - Sustaining member management and benefits tracking

## Service Layer

The application uses a service layer architecture to encapsulate business logic:

- **[MemberProfileService](services/member-profile-service.md)** - Profile management and directory operations
- **[ReservationService](services/reservation-service.md)** - Booking validation and cost calculations
- **[UserSubscriptionService](services/user-subscription-service.md)** - Membership status and benefits management

## Getting Started

Each system documentation includes:
- API reference with method signatures
- Business logic explanation
- Workflow descriptions
- Use cases and examples
- Integration points with other systems

## Technology Stack

- **Laravel 12** - PHP framework
- **Filament PHP v4** - Admin panel and forms
- **Spatie Packages** - Permission management, tagging, media library, model flags
- **SQLite** - Database (development)
- **Pest** - Testing framework