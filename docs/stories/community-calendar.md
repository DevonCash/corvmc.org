# Community Calendar User Stories

## Overview

The Community Calendar is a platform for members to submit and advertise their own publicly accessible performances and events. It extends the existing calendar functionality to provide a public-facing calendar where members can showcase their concerts, shows, open performances, and public workshops to the broader community. This creates opportunities for audience building, cross-promotion, and community engagement beyond the organization's official programming.

## Core User Stories

### AS-001: Public Event Discovery

**As a** community member or potential visitor  
**I want to** view upcoming member performances at the Corvallis Music Collective  
**So that** I can discover concerts, shows, and public events by community members

**Acceptance Criteria:**

- Public calendar displays approved member events
- Events show basic details (title, date, time, venue, organizer)
- Clearly distinguish member events from official CMC programming
- Filter events by type (performances, workshops, teaching, etc.)
- Mobile-responsive calendar view
- No authentication required for viewing

### AS-002: Practice Space Availability

**As a** potential member or visitor  
**I want to** see when practice spaces are available  
**So that** I can understand facility usage and plan potential bookings

**Acceptance Criteria:**

- Show general availability without exposing private details
- Display as "Available" or "Reserved" time blocks
- Don't show specific user names or reservation details
- Include operating hours (9 AM - 10 PM)
- Show recurring reservation patterns

### AS-003: Member Event Submission

**As a** CMC member  
**I want to** submit my own performances to the community calendar  
**So that** I can advertise my shows, concerts, and public events to build an audience

**Acceptance Criteria:**

- Members can submit their own events through web form
- Include event details (title, description, date, time, venue, type)
- Address/location validation with distance checking from Corvallis
- Option to mark events as member-only or public
- Approval requirements based on member trust level (see AS-017)
- Email notification to staff for review (when required)
- Submitter receives confirmation and status updates
- Link to member's profile from event

### AS-004: Event Categories and Filtering

**As a** calendar viewer  
**I want to** filter events by category/type  
**So that** I can find events that match my interests

**Acceptance Criteria:**

- Event categories: Member Performances, Public Workshops, Open Mics, Collaborative Shows, Album Releases
- Toggle filters on/off
- Clear visual distinction between event types and organizers
- Preserve filter settings in browser session
- Filter by member vs. official CMC events
- Option to show only local events (within configured distance limit)
- Location/venue filter for repeat venues

### AS-005: Calendar Integration and Sync

**As a** community member  
**I want to** add CMC events to my personal calendar  
**So that** I don't miss events I'm interested in

**Acceptance Criteria:**

- Export individual events to personal calendar (ICS format)
- Subscribe to calendar feed for automatic updates
- Include event details in calendar export
- Support major calendar platforms (Google, Apple, Outlook)

### AS-006: Recurring Member Events

**As a** member  
**I want to** create recurring events for my regular public performances  
**So that** my ongoing shows or public workshops appear consistently on the calendar

**Acceptance Criteria:**

- Create weekly/monthly recurring events (e.g., "Jazz Trio Fridays at [Venue]")
- Set start and end dates for recurring series
- Ability to modify individual instances
- Bulk edit options for series changes
- Members manage their own recurring public events

### AS-007: Event Details and Media

**As a** calendar viewer  
**I want to** see detailed information about events  
**So that** I can make informed decisions about attendance

**Acceptance Criteria:**

- Click event for detailed popup/modal
- Show description, performers, ticket info
- Display event poster/images if available
- Link to external ticket sales if applicable
- Share event via social media or link

### AS-008: Member Event Management Dashboard

**As a** CMC member  
**I want to** manage all my submitted performances in one place  
**So that** I can track my event submissions and promote my shows effectively

**Acceptance Criteria:**

- Dashboard showing all my submitted events (pending, approved, past)
- Edit pending/future events before approval
- Upload and manage event media (posters, photos)
- Set event visibility (public, members-only)
- Cancel events with automatic notifications
- View event engagement metrics (views, clicks)
- Copy/duplicate previous events for recurring activities

### AS-009: Calendar Views and Navigation

**As a** calendar user  
**I want to** navigate the calendar easily  
**So that** I can find events across different time periods

**Acceptance Criteria:**

- Month, week, and day view options
- Today/current date highlighting
- Previous/next navigation
- Jump to specific date
- Responsive design for mobile devices

### AS-010: Event Notifications and Reminders

**As a** community member  
**I want to** receive notifications about upcoming events  
**So that** I don't miss events I'm interested in

**Acceptance Criteria:**

- Optional email notifications for new events
- Reminder emails for events I've marked as "interested"
- Unsubscribe option for notifications
- Event change notifications (time, cancellation)

## Sponsor/Partnership User Stories

### AS-011: Sponsor Event Bulk Management

**As a** CMC sponsor or partner organization  
**I want to** submit multiple events at once for the community calendar  
**So that** I can efficiently promote my venue's shows featuring CMC members

**Acceptance Criteria:**

- Bulk upload interface for sponsors (CSV/spreadsheet import)
- Template download with required fields (date, time, venue, performer, event type)
- Address/location validation with distance checking from Corvallis
- Batch approval workflow for sponsor events
- Sponsor branding/attribution on their submitted events
- Automatic member linking when CMC members are performers
- Sponsor dashboard showing all their submitted events
- Bulk edit capabilities for event series or corrections

### AS-012: Sponsor Event Integration

**As a** sponsor  
**I want to** seamlessly integrate CMC member performances at my venue into the community calendar  
**So that** I can provide value to members while promoting my business

**Acceptance Criteria:**

- Pre-approved sponsor status for trusted venues/organizations
- Automatic calendar integration via API or feed
- Member notification when they're featured in sponsor events
- Co-promotion tools (sponsor logo + member profile linking)
- Analytics for sponsor event engagement and member reach
- Sponsor event categories (venue shows, festivals, collaborations)

### AS-013: Member-Sponsor Event Coordination

**As a** CMC member  
**I want to** coordinate with sponsors about events featuring my performances  
**So that** my shows are accurately represented and promoted

**Acceptance Criteria:**

- Members can claim/verify their involvement in sponsor-submitted events
- Notification system when sponsors add member performances
- Member ability to add details to sponsor events (setlist, collaborators, etc.)
- Joint promotion capabilities between member and sponsor
- Revenue/ticket sharing transparency when applicable

## Staff/Admin User Stories

### AS-014: Event Moderation and Trust Management

**As a** staff member  
**I want to** manage event approvals and member trust levels efficiently  
**So that** calendar content remains appropriate while reducing moderation overhead

**Acceptance Criteria:**

- Staff dashboard for pending event approvals (trust-filtered)
- Preview event details before approval
- Approve, reject, or request changes
- Add staff notes/comments and trust adjustments
- Notification to submitter on decision
- Bulk approval tools for trusted members
- Trust level adjustment interface

### AS-015: Calendar Administration

**As a** staff member  
**I want to** manage calendar settings and content  
**So that** the community calendar serves organizational goals

**Acceptance Criteria:**

- Configure event categories and types
- Set approval requirements by event type and submitter role
- Configure maximum distance limits for events (default: 60 minutes from Corvallis)
- Moderate existing events (edit, hide, remove)
- Export calendar data for reporting
- Manage calendar permissions and roles
- Sponsor account management and pre-approval settings
- Bulk approval tools for sponsor event batches

### AS-016: Integration with Existing Systems

**As a** staff member  
**I want to** see community events alongside internal scheduling  
**So that** I can identify conflicts and optimize space usage

**Acceptance Criteria:**

- Admin calendar shows member events, sponsor events, and internal events
- Conflict detection between community events and reservations
- Different visual styling for event types and sources (member/sponsor/official)
- Override capability for space conflicts
- Sponsor event verification and member linking tools

## Technical Requirements

### Integration Points

- Extend existing `CalendarService` to handle community events
- Leverage `guava/calendar` package for UI components
- Use existing `Production` model or create new `CommunityEvent` model
- Integration with Filament admin for event management

### Data Model Considerations

- Event visibility levels (public, members-only, staff-only)
- Approval workflow states (pending, approved, rejected, auto-approved)
- Trust-based moderation system with member trust levels and points
- Event reporting system with violation tracking
- Event categories and tagging system
- Media attachments for events
- Contact information for event organizers
- Trust metrics and historical performance tracking
- Sponsor account types and permissions
- Bulk event import/export capabilities
- Member-sponsor event linking and verification system
- Location validation and distance calculation system
- Venue database with pre-approved locations and travel times

### Performance and Caching

- Public calendar should be cached for performance
- Real-time updates for staff approval actions
- Efficient querying for calendar views
- Consider CDN for event media

## Future Enhancements

### AS-022: Event Attendance Tracking

**As an** event organizer  
**I want to** track event attendance  
**So that** I can understand event success and plan future events

### AS-023: Collaborative Event Planning

**As a** member  
**I want to** collaborate with others on event planning  
**So that** we can organize community events together

### AS-024: Event Series and Festivals

**As a** staff member  
**I want to** group related events into series or festivals  
**So that** complex programming is presented clearly

### AS-025: Room-Specific Event Booking

**As an** event organizer  
**I want to** book specific rooms for events  
**So that** I can plan appropriate events for available spaces

### AS-020: Location Distance Validation

**As a** staff member  
**I want to** ensure events are within reasonable driving distance from Corvallis  
**So that** calendar events are relevant and accessible to the local community

**Acceptance Criteria:**

- Automatic distance calculation from Corvallis, OR for all event addresses
- Configurable maximum distance limits (suggested: 60 minutes driving time)
- Warning for events beyond reasonable distance with manual override option
- Background distance validation (not displayed to users)
- Integration with mapping service (Google Maps API) for accurate calculations
- Pre-approved venue list for common locations (Portland venues, coast venues, etc.)
- Bulk distance validation for sponsor event uploads

### AS-021: Event Location Display

**As a** calendar viewer  
**I want to** see location details for events  
**So that** I can plan attendance and understand accessibility

**Acceptance Criteria:**

- Display venue name and address
- Link to map/directions for each event
- Venue type indicators (venue, house show, outdoor, etc.)
- Public transit information when available
- Accessibility information for venues
- Parking availability indicators

### AS-026: Integration with Member Profiles

**As a** member  
**I want to** showcase my performances on my member profile  
**So that** other members and visitors can discover my shows and public events

### AS-017: Trust-Based Moderation System

**As a** member  
**I want to** earn trust through good event submissions  
**So that** my future events can be published immediately without staff review

**Acceptance Criteria:**

- New members start with "Pending" trust level (all events require approval)
- Trust levels: Pending → Trusted → Verified → Auto-Approved
- Members earn trust points for events without reports (1 point per successful event)
- Members lose trust points for content violation reports (3-5 points per violation)
- Trust level thresholds: Trusted (5 points), Verified (15 points), Auto-Approved (30 points)
- Auto-approved members can publish events immediately
- Events from trusted+ members appear with "fast-track" approval (24hr review)
- Trust points visible in member dashboard
- Automatic trust level adjustments based on point thresholds

### AS-018: Community Event Reporting

**As a** community member  
**I want to** report inappropriate or inaccurate events  
**So that** the calendar maintains quality and appropriate content

**Acceptance Criteria:**

- Report button on all public events
- Report categories: Inappropriate content, Spam, Inaccurate info, Commercial violation
- Anonymous reporting option
- Report tracking and staff notification
- Reporter feedback on resolution
- Repeated reports trigger automatic event review
- False report detection to protect against abuse

### AS-019: Trust Level Display and Transparency

**As a** calendar viewer  
**I want to** see event organizer trust indicators  
**So that** I can make informed decisions about event credibility

**Acceptance Criteria:**

- Trust badges on events from verified/auto-approved members
- Member profile shows trust level (with privacy controls)
- "Verified organizer" indicators on calendar
- Trust level affects event prominence in listings
- Historical event success rate visible on member profiles
