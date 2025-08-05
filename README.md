# Corvallis Music Collective

This is a site for the Corvallis Music Collective, a nonprofit organization operating out of Corvallis, Oregon.

## Critical Features

Features are listed in order of priority. Each should be implemented in an isolated module using as many pre-existing tools as possible. Moderation and access to each feature should be handled on an individual basis to allow volunteers to take on a subset of features for moderation.

### Membership (IMPLEMENTED - Zeffy Integration Pending)

**Current Implementation Status:**
- ✅ Complete user registration and authentication system
- ✅ Role-based membership management with Spatie Permissions
- ✅ Sustaining member role and permissions system
- ✅ Transaction model for payment tracking
- ✅ Staff access controls for moderation tools

**Phase 1 (Completed):**
- ✅ User registration and authentication via Filament
- ✅ Basic member profile management
- ✅ Admin interface for user management

**Phase 2 (Mostly Implemented):**
- ✅ Members automatically created on registration
- ✅ Sustaining member role system with enhanced permissions
- ✅ Staff roles with granular access to moderation tools
- ✅ Transaction tracking infrastructure
- ⏳ Automated sustaining member detection based on $10+ monthly donations

**Pending Integration:**
- ⏳ Zeffy webhook integration for payment processing
- ⏳ Automated role assignment based on payment status

**Technical Implementation:**
- **Authentication**: Laravel Filament authentication with custom member panel
- **Roles**: Comprehensive role system (member, sustaining member, staff, admin)
- **Payments**: Transaction model ready for Zeffy webhook integration
- **Admin Interface**: Full user management via Filament resources

### Productions (FULLY IMPLEMENTED - Minor Enhancements Pending)

**Current Implementation Status:**
- ✅ Complete production management system with Filament admin interface
- ✅ Production lifecycle management (pre-production → published → completed/cancelled)
- ✅ Band lineup management with set ordering and duration tracking
- ✅ Staff assignment with manager-based permissions
- ✅ Media management for posters and promotional materials
- ✅ Location data structure for venue information
- ✅ Genre tagging system integration
- ✅ Production duplication functionality for recurring events
- ✅ **Public event listing and detail pages** (`/events` routes)
- ✅ **Advanced search and filtering** (genre, date, venue)
- ✅ **Ticket URL and pricing integration**
- ✅ **Practice space conflict detection**
- ✅ **Homepage event integration**

**Phase 1 Requirements (Completed):**
- ✅ Production creation and management interface for staff
- ✅ Event scheduling with start/end times and doors times
- ✅ Production status workflow (pre-production, published, completed, cancelled)
- ✅ Manager assignment and permission-based access control
- ✅ Advanced venue/location information with LocationData DTOs

**Phase 2 Requirements (Completed):**
- ✅ Centralized staff management area via Filament admin panel
- ✅ Performer lineup management with drag-and-drop ordering
- ✅ Set length tracking for scheduling and planning
- ✅ Production transfer capabilities between managers
- ✅ Comprehensive production search and filtering
- ✅ Production statistics and reporting

**Phase 3 Requirements (Mostly Completed):**
- ✅ Public website integration for event display
- ✅ Public event calendar view with filtering by genre/date
- ✅ Individual production pages with performer details
- ✅ Integration with practice space reservations system
- ✅ Ticket sales integration (URL and pricing)
- ⏳ `/show-tonight` redirect functionality to next published production
- ⏳ Email notifications for production updates
- ⏳ Public API endpoints for external calendar integrations

**Technical Implementation Details:**
- **Database**: Productions table with soft deletes, JSON location data, pivot table for band relationships
- **Permissions**: Role-based access via `spatie/laravel-permission` (manage productions, view productions)
- **Media**: Poster uploads via `spatie/laravel-medialibrary` with thumbnail generation
- **Tags**: Genre classification via `spatie/laravel-tags`
- **Service Layer**: Comprehensive `ProductionService` for business logic encapsulation
- **Admin Interface**: Filament resources with form schemas, relation managers, and table configurations
- **Public Interface**: Full public event browsing with search, filtering, and detail views

### Practice Space Scheduling (FULLY IMPLEMENTED - Admin Interface Only)

**Current Implementation Status:**
- ✅ Complete reservation management system with Filament admin interface
- ✅ Sophisticated pricing logic ($15/hour with 4 free hours for sustaining members)
- ✅ Payment tracking with multiple payment methods (cash, card, Venmo, PayPal, etc.)
- ✅ Conflict detection with productions and other reservations
- ✅ Calendar integration for visual scheduling
- ✅ Recurring reservation support for sustaining members
- ✅ Business rules enforcement (9 AM-10 PM, 1-8 hour durations)

**Phase 1 (Fully Implemented):**
- ✅ Complete reservation system for practice space scheduling
- ✅ $15/hour rate with automatic calculation
- ✅ 4 free hours per month for sustaining members with rollover tracking
- ✅ Payment status management (unpaid/paid/comped/refunded)
- ✅ Reservation conflict detection using Spatie Period library

**Phase 2 (Fully Implemented):**
- ✅ Recurring reservations for sustaining members
- ✅ Advanced approval workflow and status management
- ✅ Sophisticated conflict detection across reservations and productions
- ⏳ Waitlisting system (not yet implemented)

**Technical Implementation:**
- **Database**: Comprehensive reservations table with payment tracking
- **Business Logic**: `ReservationService` with complex pricing and conflict detection
- **Calendar**: Visual calendar interface for staff scheduling
- **Permissions**: Role-based access control for reservation management
- **Payment Methods**: Support for cash, card, digital payments, and comps

**Note**: Currently admin-interface only. No public booking interface is specified or implemented - all reservations managed through Filament admin panel.

### Member Directory (FULLY IMPLEMENTED)

**Current Implementation Status:**
- ✅ Complete public member directory with search and filtering
- ✅ Rich member profiles with skills, bio, and contact information
- ✅ Avatar management with thumbnail generation
- ✅ Skills and genre tagging system via Spatie Tags
- ✅ Visibility controls (public/members/private)
- ✅ Professional designation system with flags
- ✅ Individual member profile pages

**Phase 1 (Completed):**
- ✅ Comprehensive skills and abilities listing system
- ✅ Public member directory (`/members` route)
- ✅ Advanced search by name, skills, location, and instruments
- ✅ Professional profile management

**Phase 2 (Completed):**
- ✅ Public profile visibility for sustaining members and all users
- ✅ Teachers and professionals designation via flag system
- ✅ Enhanced profiles with bio, hometown, influences, and social links
- ✅ Contact information management

**Technical Implementation:**
- **Database**: `member_profiles` table with comprehensive user information
- **Media**: Avatar uploads via `spatie/laravel-medialibrary`
- **Tags**: Skills and genres via `spatie/laravel-tags`
- **Public Interface**: Searchable member directory with filtering
- **Permissions**: Visibility controls and role-based access

### Band Directory (FULLY IMPLEMENTED)

**Current Implementation Status:**
- ✅ Complete band profile management system
- ✅ Public band directory with search and filtering (`/bands` route)
- ✅ Comprehensive band member management with invitation system
- ✅ Social media integration and avatar management
- ✅ Genre and influence tagging system
- ✅ Band member roles and permissions (owner, admin, member)
- ✅ EPK (Electronic Press Kit) functionality with media management

**Phase 1 (Completed):**
- ✅ Band profile creation and management interface
- ✅ Social media links and avatar management
- ✅ Public band directory with "web ring" browsing functionality
- ✅ Individual band profile pages with detailed information
- ✅ Band member invitation and management system

**Phase 2 (Completed):**
- ✅ Full EPK functionality with file hosting via `spatie/laravel-medialibrary`
- ✅ Media management for promotional materials and press photos
- ✅ Integration with productions system for event management
- ✅ Available to all users (not restricted to sustaining members)

**Technical Implementation:**
- **Database**: `band_profiles` table with member pivot relationships
- **Media**: Avatar and EPK uploads via `spatie/laravel-medialibrary`
- **Tags**: Genre classification via `spatie/laravel-tags`
- **Permissions**: Role-based band member management (owner, admin, member)
- **Notifications**: Email notifications for band invitations
- **Public Interface**: Searchable band directory with individual band pages

## Planned Features

Planned features are not ordered by priority

### Community Calendar

Allow members to submit their own events to be displayed on a public calendar. Provide notifications and pubsub feeds. Allow following based on tags, etc.

### Sponsorship management

Tools for sponsors to manage their contribution and rewards and control the branding we use on co-branded material

### User Groups

Member club management tools, emphasis on in-person meetups over online discussion. Include features to support band formation, etc.

### Flyering Tools and introspection

Use unique flyer qr codes to track performance of poster locations, allow for a/b testing, link substitution, etc. Additionally, sustaining members can have their posters hung by our street team.

### Equipment Management Tools

Phase 1: Track make, model, and maintenance on equipment the collective is responsible for, and keep track of who owns it (if it's leant) or who donated it

Phase 2: Allow for reservation and lending of managed equipment, scheduling around maintenance and prioritizing in-house use.

### Equipment Exchange

Allow members to post equipment for local purchase/exchange.

### Custom Theming

We want to support a wide variety of musicians on the site, so it would be nice to allow a degree of customization for member and band profiles. This could just be custom colors, but it would be fun to allow the custom css style customization that myspace granted access to.

### Music hosting and streaming

Allow bands to upload their own music to be played on the website and opt-in to be used at CMC events and in our business-music package.

### Volunteer hour tracking

We can set up a threshold for unlocking sustaining membership with volunteer hours
