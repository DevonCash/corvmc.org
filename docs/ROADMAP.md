# Roadmap

This document tracks planned enhancements and future features for the Corvallis Music Collective platform.

## Audience-Based Panel Architecture (Critical Priority)

### Overview
Major refactor to reorganize the platform around distinct user audiences rather than functional modules. This creates purpose-built experiences for each user type while maintaining shared infrastructure.

### Proposed Panel Structure
- **`/member`** - Member Portal (General Members + Band Members)
  - Contextual navigation based on user roles
  - Band features only appear for band members
  - Warm, welcoming amber theme

- **`/staff`** - Operations Hub (CMC Staff, Volunteer Coordinators)
  - Aggregated operational views
  - Approval workflows and admin tools
  - Professional blue theme

- **`/business`** - Professional Services Portal (External Clients) - Future
  - Client self-service for production services
  - Project management and invoicing

- **`/partners`** - Sponsor Portal - Future
  - Sponsorship management and impact reporting

- **`/create`** - Creator Studio - Future
  - Content submission and earnings tracking

### Implementation Strategy
- Backend remains modular using Action-based architecture
- Frontend organized by audience for better UX
- Dynamic navigation based on user context/roles
- Progressive disclosure of advanced features

### Technical Approach
- Leverage existing `spatie/laravel-permission` for role detection
- Use Filament's panel system with conditional navigation
- Maintain modular Actions organization
- Smart dashboards with contextual widgets

### Benefits
- Users stay in "their" panel instead of context switching
- Better feature discoverability
- Clearer mental model for different user types
- Scalable - add features within existing panels

### Reference
See `docs/audience-based-architecture.md` for complete specification

---

## Volunteer Management (High Priority)

### Core Volunteer System (Planned)
Comprehensive volunteer management system for events and ongoing operations:
- **Volunteer profiles** - Extended user profiles with skills, availability, interests
- **Shift scheduling** - Create and manage volunteer shifts for events/productions
- **Sign-up system** - Allow volunteers to claim available shifts
- **Hour tracking** - Record volunteer hours for recognition and reporting
- **Task management** - Assign specific roles/tasks (door, sound, merch, setup, etc.)
- **Communication** - Notify volunteers of upcoming shifts and updates
- **Reporting** - Generate volunteer hour reports for grants and recognition

### Integration Points
- **Productions** - Link volunteer shifts to specific events/shows
- **Member profiles** - Extend existing user system
- **Notifications** - Email/system notifications for shift reminders
- **Permissions** - Role-based access for volunteer coordinators

### Technical Considerations
- Leverage existing Action-based architecture
- Use `spatie/laravel-permission` for volunteer coordinator roles
- Consider recurring shift patterns similar to recurring reservations
- Integration with production/event calendar

---

## Community Calendar (High Priority)

### Overview
Public calendar system for community members to post and discover local music events, meetups, and activities beyond CMC productions.

### Core Features
- **Event submission** - Members can submit community events for approval
- **Calendar views** - Month, week, and list views of community events
- **Event categories** - Shows, open mics, jam sessions, workshops, meetups
- **Filtering** - By category, venue, date range
- **Venue management** - Community venue directory
- **RSVP tracking** - Optional attendance tracking for community events
- **Moderation** - Staff approval workflow for submitted events

### Integration Points
- **Member profiles** - Link events to member/band profiles
- **Productions** - Separate from CMC productions (different model/table)
- **Notifications** - Email notifications for new events in areas of interest
- **Public display** - Viewable on public site for discovery

### Technical Considerations
- Separate `community_events` table from `productions`
- Uses existing Action-based architecture
- Staff approval workflow using `spatie/laravel-permission`
- Optional integration with external calendars (iCal export)
- Settings toggle via existing `CommunityCalendarSettings`

### Benefits
- Strengthens community connections
- Increases platform engagement
- Promotes local music scene
- Drives traffic to member/band profiles

### Future Consideration
Current `Production` system features are underutilized. Once Community Calendar is implemented, may consolidate to use Community Calendar exclusively, significantly simplifying the event management architecture.

---

## Reservation System Enhancements

### Configurable Reservation Settings (Planned)
Add a Reservation Settings section to the Site Settings page to allow admins to configure:
- **Business hours** - Configurable start/end times (currently hardcoded to 9 AM - 10 PM in `config/reservation.php`)
- **Hourly rate** - Adjustable pricing for practice space (currently $15/hour)
- **Duration limits** - Min/max reservation lengths (currently 1-8 hours)
- Store settings using `spatie/laravel-settings` similar to existing OrganizationSettings

### Holiday and Closure Management (Planned)
Add ability for admins to close the practice space for specific dates:
- **Holiday calendar** - Mark specific dates as closed (holidays, maintenance, etc.)
- **Closure scheduling** - Set temporary closures with date ranges
- **Validation integration** - Prevent reservations on closed dates
- **Public display** - Show closures on booking calendar
- Consider using a dedicated `space_closures` table with:
  - `date` or `start_date`/`end_date` for date ranges
  - `reason` (holiday name, maintenance, etc.)
  - `is_recurring` for annual holidays
  - Integration with existing `ValidateTimeSlot` action

---

## Priority Levels

- **High** - Critical for operations
- **Medium** - Important for user experience
- **Low** - Nice to have, can be deferred

## Contributing

When adding to this roadmap:
1. Place items in relevant sections
2. Include brief description of the feature
3. Note any dependencies or technical requirements
4. Update status as work progresses
