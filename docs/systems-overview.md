# Corvallis Music Collective - Systems Overview

This document provides a comprehensive overview of all systems within the Corvallis Music Collective application, their current implementation status, and management capabilities.

## Application Architecture

**Framework:** Laravel 12  
**Admin Interface:** Filament PHP v4  
**Database:** SQLite (development), supports production databases  
**Asset Pipeline:** Vite with TailwindCSS v4  
**Testing:** Pest framework with custom test commands  

## Core Systems

### 1. User Management & Authentication System

**Status: ✅ Complete and Production-Ready**

**Overview:** Comprehensive user management system with role-based access control and secure invitation workflow.

**Key Features:**

- Token-based secure invitation system with email notifications
- Role assignment during invitation (member, band leader, sustaining member, admin)
- Invitation acceptance with automatic user verification
- Invitation statistics and acceptance rate tracking
- Resend and cancellation capabilities for pending invitations

**Management Tools:**

- Admin can invite users with specific roles
- Resend invitation action for pending users
- Bulk cancel pending invitations
- Filter users by invitation status (invited/registered)
- Comprehensive test command: `php artisan test:invitations`

**Technical Components:**

- Service: `UserInvitationService`
- Model: `User` (with Spatie roles integration)
- Admin Interface: Filament User Resource with custom actions
- Web Interface: Public invitation acceptance forms

---

### 2. Member Profile System

**Status: ✅ Complete with Advanced Features**

**Overview:** Rich member profiles with media, skills, and visibility controls.

**Key Features:**

- Comprehensive profile information (bio, skills, influences, contact info)
- Media library integration for avatars and galleries
- Tagging system for skills, genres, and influences using Spatie Tags
- Visibility controls (public, members-only, private)
- Model flags for special designations (teacher, performer, touring, etc.)

**Management Tools:**

- Public searchable member directory
- Configurable member flags through organization settings
- Profile visibility scope management
- Media management with automatic thumbnails

**Technical Components:**

- Service: `MemberProfileService`
- Model: `MemberProfile` (with tags, media, flags)
- Public Interface: `MembersGrid` Livewire component
- Settings: `MemberDirectorySettings` for configuration

---

### 3. Band Profile Management System

**Status: ✅ Complete with Complex Membership Features**

**Overview:** Multi-member band management with invitation system and role hierarchy.

**Key Features:**

- Band creation with owner, admin, and member roles
- Band invitation system with status tracking (invited, active, declined)
- Position and role management for band members
- Media management for band photos and promotional materials
- Genre and influence tagging system
- Ownership transfer capabilities

**Management Tools:**

- Band invitation page for managing member invitations
- Member relationship manager with role assignment
- Public band directory with search functionality
- Bulk member management actions

**Technical Components:**

- Service: `BandService` for membership operations
- Model: `BandProfile` with complex member relationships
- Admin Interface: Dedicated band invitation management page
- Public Interface: `BandsGrid` Livewire component

---

### 4. Practice Space Reservation System

**Status: ✅ Complete with Advanced Scheduling**

**Overview:** Sophisticated practice space booking system with conflict detection and cost calculation.

**Key Features:**

- Time slot reservation with conflict detection using Spatie Period
- Sustaining member benefits (4 free hours/month)
- Dynamic cost calculation based on membership status
- Business hours enforcement (9 AM - 10 PM)
- Recurring reservation support
- Calendar integration with multiple view types

**Management Tools:**

- Monthly, weekly, and daily calendar widgets
- Reservation status management (pending, confirmed, cancelled)
- Cost tracking and payment status
- Statistical reporting for space utilization
- Conflict detection for overlapping reservations

**Technical Components:**

- Service: `ReservationService` with cost calculation logic
- Model: `Reservation` with time conflict validation
- Calendar Widgets: Multiple Filament calendar widgets
- Integration: Practice space conflicts with event scheduling

---

### 5. Event & Production Management System

**Status: ✅ Complete with Rich Features**

**Overview:** Comprehensive event management with performer scheduling and publication workflow.

**Key Features:**

- Event creation for internal and external venues
- Performer lineup management with set length tracking
- Publication workflow (draft → published states)
- Ticket integration with pricing and NOTAFLOF support
- Media management for event posters and galleries
- Calendar integration preventing practice space conflicts during events

**Management Tools:**

- Event scheduling with automatic conflict detection
- Performer assignment and set time calculation
- Venue management (CMC space vs external venues)
- Publication scheduling for public event listings
- Ticket URL and pricing management

**Technical Components:**

- Service: `ProductionService` for event operations
- Model: `Production` with performer relationships
- Public Interface: `EventsGrid` with search and filtering
- Integration: Automatic practice space blocking during events

---

### 6. Financial Management & Subscription System

**Status: ✅ Complete with Webhook Integration**

**Overview:** Automated financial tracking with third-party payment processor integration.

**Key Features:**

- Transaction tracking via Zeffy/Zapier webhook integration
- Automatic sustaining membership determination ($10+ monthly donations)
- Free practice hours allocation for sustaining members
- Donation acknowledgment notification system
- Revenue tracking and membership statistics

**Management Tools:**

- Webhook endpoint for automatic transaction processing
- Manual membership status override capabilities
- Donation tracking and thank you automation
- Monthly statistics for revenue and membership growth
- Free hours calculation and balance tracking

**Technical Components:**

- Service: `UserSubscriptionService` for membership logic
- Model: `Transaction` for financial record keeping
- Webhook: `ZeffyWebhookController` for payment processing
- Integration: Automatic role assignment based on donation levels

---

### 7. Notification & Communication System

**Status: ✅ Complete with Comprehensive Coverage**

**Overview:** Multi-channel notification system covering all major application events.

**Key Features:**

- Email and database notifications for all major actions
- User invitation and acceptance notifications
- Band invitation and membership change notifications
- Reservation confirmation and reminder notifications
- Donation acknowledgment and thank you notifications
- Production and event-related notifications

**Management Tools:**

- Comprehensive test command for all notification types
- Notification preference management
- Email template customization
- Automated reminder scheduling

**Technical Components:**

- 7+ notification classes covering all major events
- Test Command: `php artisan test:notifications` with send/dry-run modes
- Queue integration for reliable delivery
- Database persistence for notification history

---

### 8. Public Website System

**Status: ✅ Complete with Modern Interface**

**Overview:** Public-facing website with member/band/event directories and organizational information.

**Key Features:**

- Responsive design with mobile drawer navigation
- Searchable directories for members, bands, and events
- Dynamic homepage with community statistics
- Contact form with validation and notification
- SEO-friendly individual pages for bands and events

**Management Tools:**

- Dynamic content management through admin interface
- Visibility controls for public directory listings
- Contact form submission handling
- Automatic statistics calculation for homepage

**Technical Components:**

- Livewire components for interactive search and filtering
- Public view templates with consistent design system
- Contact form with server-side validation
- Statistics calculation for community metrics

---

### 9. Admin Panel & Management Interface

**Status: ✅ Complete with Filament v4**

**Overview:** Unified administrative interface for all system management.

**Key Features:**

- Single Filament panel with role-based access control
- CRUD operations for all major entities
- Custom dashboard widgets and statistics
- Settings management for organization configuration
- Relationship management through dedicated relation managers

**Management Tools:**

- User, member, band, reservation, and production resources
- Calendar widgets for reservation and event overview
- Statistical dashboards and reporting
- Organization settings management
- Custom pages for specialized workflows (band invitations)

**Technical Components:**

- Filament v4 with custom panel provider
- Resource-specific form schemas and table configurations
- Custom actions and bulk actions for workflow management
- Settings integration using Spatie Laravel Settings

---

### 10. Testing & Quality Assurance System

**Status: ✅ Complete with Custom Test Commands**

**Overview:** Comprehensive testing infrastructure with automated workflow validation.

**Key Features:**

- Custom artisan commands for testing complex business logic
- End-to-end workflow testing for critical systems
- Pest testing framework with feature and unit tests
- Test data cleanup and isolation

**Management Tools:**

- `php artisan test:invitations --clean` - Complete invitation system testing
- `php artisan test:notifications --send` - All notification types testing
- Automated test data generation and cleanup
- Dry-run capabilities for safe testing

**Technical Components:**

- Custom test commands with comprehensive coverage
- Feature tests for user workflows
- Unit tests for model behavior and business logic
- Test database isolation and cleanup

---

## System Integration Matrix

All systems are fully integrated with sophisticated cross-system dependencies:

| System | Integrates With | Integration Type |
|--------|----------------|------------------|
| **User Management** | All Systems | Authentication, Authorization, Relationships |
| **Member Profiles** | User Management, Band Profiles | Profile Extensions, Band Membership |
| **Band Profiles** | Member Profiles, Productions | Member Management, Event Performers |
| **Reservations** | User Management, Productions | Membership Benefits, Conflict Detection |
| **Productions** | Reservations, Band Profiles | Space Blocking, Performer Assignment |
| **Financial** | User Management, Reservations | Membership Status, Cost Calculation |
| **Notifications** | All Systems | Event-driven Communication |
| **Public Website** | All Content Systems | Public Data Display |
| **Admin Panel** | All Systems | Unified Management Interface |

## Technical Architecture Highlights

### Advanced Package Integration

- **Spatie Laravel Permission** - Role-based access control
- **Spatie Laravel Tags** - Flexible tagging system
- **Spatie Laravel Media Library** - File management with thumbnails
- **Spatie Laravel Settings** - Configuration management
- **Spatie Period** - Time conflict detection
- **Guava Calendar** - Calendar component integration

### Enterprise-Level Features

- **Webhook Integration** for real-time payment processing
- **Advanced Time Conflict Detection** using mathematical period calculations
- **Sophisticated Cost Calculation** with membership benefits
- **Multi-channel Notification System** with queue integration
- **Comprehensive Testing Infrastructure** with custom commands
- **Model Flags System** for flexible entity categorization

### Development & Deployment

- **Laravel 12** with modern PHP practices
- **Filament v4** for administrative interfaces
- **Pest Testing** with custom workflow commands
- **Vite Build System** with TailwindCSS v4
- **Code Style Enforcement** with Laravel Pint
- **Comprehensive Documentation** and system overviews

## System Health Assessment

✅ **All systems are production-ready and fully operational**

Every system demonstrates enterprise-level Laravel development with:

- Proper service layer architecture
- Comprehensive error handling
- Automated testing capabilities
- Security best practices
- Performance optimizations
- Extensive documentation

The application serves as a complete solution for nonprofit music collective management with sophisticated features that extend well beyond basic CRUD operations.

---

*Last Updated: August 2025*  
*Document Generated: Corvallis Music Collective Systems Analysis*
