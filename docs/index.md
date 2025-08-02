# Corvallis Music Collective - Complete System Documentation

## Overview

The Corvallis Music Collective platform is a comprehensive Laravel-based web application designed to manage all aspects of a nonprofit music collective. The system facilitates member profiles, band management, event production, practice space reservations, and sustaining membership programs.

## System Architecture

### Core Models & Systems
- **[Member Profiles](member-profiles.md)** - Individual musician profiles with skills, visibility, and directory features
- **[Band Profiles](band-profiles.md)** - Band management with member invitations and collaboration tools
- **[Productions](productions.md)** - Event and show management with ticketing and venue coordination
- **[Reservations](reservations.md)** - Practice space booking with pricing and availability management
- **[User Subscriptions](user-subscriptions.md)** - Sustaining member program with automated benefit tracking

### Service Layer
- **[MemberProfileService](services/member-profile-service.md)** - Profile management and directory operations
- **[ReservationService](services/reservation-service.md)** - Booking validation, cost calculation, and scheduling
- **[UserSubscriptionService](services/user-subscription-service.md)** - Membership status and benefits management

## Key Features

### Member Management
- **Directory System**: Searchable member profiles with skills, genres, and availability
- **Visibility Controls**: Three-tier privacy system (public, members, private)
- **Collaboration Matching**: Automated suggestions based on musical compatibility
- **Profile Media**: Avatar management with thumbnail generation

### Band Ecosystem
- **Band Creation**: Owner-managed bands with flexible member roles
- **Member Invitations**: Structured invitation and acceptance workflow
- **Performance Booking**: Integration with production system for show booking
- **Media Management**: Band photos and promotional materials

### Event Production
- **Show Management**: Complete event lifecycle from planning to execution
- **Multi-Band Support**: Performance ordering and set time management
- **Ticketing Integration**: External ticket sales with NOTAFLOF support
- **Venue Flexibility**: Support for both internal and external venues

### Practice Space System
- **Hybrid Pricing**: $15/hour with sustaining member benefits (4 free hours/month)
- **Smart Scheduling**: Conflict prevention and availability checking
- **Recurring Bookings**: Weekly recurring reservations for sustaining members
- **Business Rules**: Operating hours, duration limits, and advance booking requirements

### Sustaining Membership
- **Dual Qualification**: Role-based and transaction-based membership paths
- **Automated Processing**: Recurring donation processing with automatic benefit granting
- **Benefit Tracking**: Monthly free hour allocation with usage analytics
- **Administrative Oversight**: Comprehensive reporting and manual override capabilities

## Technical Implementation

### Framework & Libraries
- **Laravel 12**: Core PHP framework
- **Filament PHP v4**: Administrative interface and form management
- **Spatie Packages**: Permission management, tagging, media library, model flags
- **Pest Testing**: Comprehensive test coverage with 147 passing tests
- **SQLite**: Development database with migration-based schema management

### Data Architecture
- **User-Centric Design**: All features tied to user accounts with proper relationships
- **Tag-Based Classification**: Skills, genres, and influences using Spatie Tags
- **Flag System**: Directory preferences and member status flags
- **Media Integration**: File uploads and image processing via Spatie MediaLibrary
- **Role-Based Access**: Permission system for administrative functions

### Service Layer Pattern
- **Business Logic Separation**: Complex operations encapsulated in dedicated services
- **Consistent APIs**: Standardized method signatures across service classes
- **Transaction Safety**: Atomic operations for critical business functions
- **Comprehensive Validation**: Multi-layered validation with detailed error reporting

## Integration Points

### External Systems
- **Payment Processing**: Stripe integration for recurring donations
- **Email Services**: Member communications and automated notifications
- **Calendar Systems**: Reservation availability and scheduling interfaces
- **File Storage**: Media management with cloud storage support

### Internal Coordination
- **Cross-System Relationships**: Members → Bands → Productions → Reservations
- **Shared Services**: Common operations accessible across multiple systems
- **Event-Driven Updates**: Model events for automatic profile creation and status updates
- **Administrative Interface**: Unified Filament-based management console

## Development & Testing

### Code Quality
- **Service Layer Architecture**: Clean separation of business logic from presentation
- **Comprehensive Testing**: 147 tests covering models, services, and workflows
- **Modern PHP Standards**: PHP 8+ attributes, typed properties, and modern Laravel features
- **Documentation Coverage**: Complete API documentation for all systems and services

### Database Design
- **Migration-Based Schema**: Version-controlled database structure with descriptive timestamps
- **Proper Relationships**: Foreign keys, indexes, and constraint management
- **Soft Deletes**: Audit-friendly deletion handling for critical records
- **Performance Optimization**: Query optimization and eager loading strategies

This documentation provides complete coverage of all systems within the Corvallis Music Collective platform. Each system document includes API references, business logic explanations, usage examples, and integration details to support both development and operational activities.