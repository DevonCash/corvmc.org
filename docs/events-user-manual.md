# Events Module User Manual

This guide covers all features of the Events module for the Corvallis Music Collective website.

## Table of Contents

1. [Overview](#overview)
2. [Public Event Discovery](#public-event-discovery)
3. [Creating Events](#creating-events)
4. [Managing Event Details](#managing-event-details)
5. [Publishing Events](#publishing-events)
6. [Managing Performers](#managing-performers)
7. [Rescheduling and Cancelling](#rescheduling-and-cancelling)
8. [Visibility and Access Control](#visibility-and-access-control)
9. [Integrations](#integrations)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The Events module allows staff to create, manage, and publish events at the Corvallis Music Collective and external venues. Events can feature multiple performers (bands), have customizable visibility settings, and automatically integrate with the practice space reservation system.

### Key Features

- Create and manage concert/event listings
- Upload event posters with automatic optimization
- Add and manage performers with set lengths
- Automatic practice space reservation for CMC events
- Flexible visibility options (public, members-only, private)
- Scheduled publishing
- Event rescheduling with TBA support
- Public event discovery with search and filtering

---

## Public Event Discovery

### Events Page (`/events`)

The public events page displays all published upcoming events in a grid layout.

**Features:**

- **Search**: Find events by title, genre, or performer name
- **Tabs**: Switch between "Upcoming" and "Past" events
- **Pagination**: 12 events per page
- **Event Cards**: Display poster, title, date/time, and pricing

### Individual Event Page (`/events/{id}`)

Each event has a dedicated page showing:

- **Event Poster**: Large display with 8.5:11 aspect ratio
- **Event Details**: Date, time, doors time, ticket price
- **NOTAFLOF Badge**: "No One Turned Away For Lack of Funds" indicator
- **Get Tickets Button**: Link to ticket purchase
- **Social Sharing**: Facebook, Twitter/X, native share
- **About Section**: Full event description
- **Featured Artists**: Grid of performer cards with bios and genres
- **Related Events**: Carousel of upcoming events

### Show Tonight (`/show-tonight`)

A convenience URL that redirects to today's event or the next upcoming event.

---

## Creating Events

### Access

Navigate to **Staff Panel > Events > Create Event** (`/staff/events/create`)

**Required Role:** Production Manager

### Required Fields

| Field | Description |
|-------|-------------|
| **Title** | Event name (required for publishing) |
| **Start Date & Time** | When the event begins |

### Optional Fields

| Field | Description |
|-------|-------------|
| **Description** | Rich text description with formatting support |
| **End Date & Time** | When the event ends |
| **Doors Time** | When doors open for attendees |
| **Venue** | Location (defaults to CMC) |
| **Event Type** | Category of event |
| **Ticket Price** | Single price point (leave blank for "various prices") |
| **Ticket URL** | Link to purchase tickets |
| **Event Link** | Alternative link (Facebook event, etc.) |
| **NOTAFLOF** | Check if event supports sliding scale pricing |

### Event Poster

Upload an event poster or flyer:

- **Formats**: JPG, PNG, GIF, WebP
- **Max Size**: 4MB
- **Recommended Aspect Ratio**: 8.5:11 (standard poster)

The system automatically generates optimized versions:

- Thumbnail (200x258px)
- Medium (400x517px)
- Large (600x776px)
- Optimized (850x1100px)

---

## Managing Event Details

### Editing Events

Navigate to **Staff Panel > Events** and click on an event to edit.

### Form Layout

**Left Sidebar:**

- Publication status and "Publish Now" button
- Poster upload

**Main Content:**

- Title and description
- Date/time fields
- Ticketing information
- Venue selection

### Venue Selection

Events can be held at:

1. **CMC (Corvallis Music Collective)**: Internal venue
   - Automatically creates a practice space reservation
   - No distance calculation needed

2. **External Venues**: Other locations
   - Distance from Corvallis calculated via Google Maps
   - No space reservation created

To add a new venue, use the venue dropdown and select "Create new venue."

---

## Publishing Events

### Publication States

| State | Description |
|-------|-------------|
| **Draft** | No publication date set - not visible to public |
| **Scheduled** | Publication date is in the future - will appear automatically |
| **Published** | Publication date is in the past - live on the site |

### How to Publish

**Option 1: Publish Now**

1. Open the event in edit mode
2. Click the **Publish** button in the header
3. Confirm the action

**Option 2: Schedule Publication**

1. Open the event in edit mode
2. Set the **Published At** date/time in the left sidebar
3. Save the event

### Publishing Requirements

An event can only be published if:

- It has a title
- The user has permission (production manager or organizer)

### Notifications

When an event is published, notifications are sent to:

- Event performers (band members)
- Other interested stakeholders

---

## Managing Performers

### Adding Performers

1. Open the event in edit mode
2. Scroll to the **Performers** relation manager
3. Click **Create** to add a new performer

**For Existing Bands:**

- Search and attach from the band directory

**For Touring Bands:**
Use the "Add Touring Band" modal:

- Band Name (required)
- Location/Hometown
- Biography
- Musical Genres
- Contact Email
- Contact Phone

Touring bands are created with private visibility and no owner.

### Performer Details

| Field | Description |
|-------|-------------|
| **Set Length** | Duration in minutes |
| **Order** | Performance order (drag to reorder) |
| **Genres** | Music style tags |

### Reordering Performers

1. Click **Enable reordering**
2. Drag performers to the desired order
3. Click **Disable reordering** when done

### Inviting Band Owners

For touring bands without an owner, use the **Invite Owner** action to send an invitation email. The recipient can claim the band profile and join CMC.

---

## Rescheduling and Cancelling

### Rescheduling an Event

Click the **Reschedule Event** button in the event header.

**Option 1: New Date Known**

1. Toggle "New date is known" ON
2. Enter the new start and end date/time
3. Optionally add a reason
4. Click **Reschedule**

This creates a new event with:

- All details copied from original
- Performers and set lengths preserved
- Tags and poster copied
- Original event marked as "Postponed" and linked

**Option 2: Postponed (TBA)**

1. Toggle "New date is known" OFF
2. Optionally add a reason
3. Click **Reschedule**

This marks the original event as "Postponed" without creating a new event.

### Cancelling an Event

Click the **Cancel Event** button in the event header.

1. Optionally enter a cancellation reason
2. Click **Cancel Event** to confirm

The event status changes to "Cancelled." The cancellation reason is stored for reference.

### Event Status Reference

| Status | Description | Icon |
|--------|-------------|------|
| **Scheduled** | Normal upcoming event | Calendar check |
| **Cancelled** | Event cancelled | Calendar X |
| **Postponed** | Rescheduled or TBA | Calendar pause |
| **At Capacity** | Venue is full | Calendar exclamation |

---

## Visibility and Access Control

### Visibility Levels

| Level | Who Can See |
|-------|-------------|
| **Public** | Everyone (including guests) |
| **Members Only** | Logged-in members |
| **Private** | Only organizer and staff |

### Access Rules

| Scenario | Access |
|----------|--------|
| Unpublished event | Only production managers and organizer |
| Published public event | Anyone |
| Published members-only event | Logged-in members, managers, organizer |
| Published private event | Only managers and organizer |

### Permissions

| Action | Who Can Perform |
|--------|-----------------|
| Create event | Production managers |
| Edit event | Production managers, organizer |
| Delete event | Production managers, organizer |
| Publish event | Production managers, organizer |
| Cancel event | Production managers, organizer |
| Reschedule event | Production managers, organizer |

---

## Integrations

### Practice Space Reservations

**Automatic Sync for CMC Events**

When an event is created or updated at the CMC venue:

1. A space reservation is automatically created
2. Reservation includes setup and breakdown time:
   - **Setup**: 2 hours before event start
   - **Breakdown**: 1 hour after event end
3. Reservation is marked as "Confirmed"
4. Blocks the practice space for member bookings

**What Triggers Sync:**

- Creating a new event at CMC
- Updating event times
- Changing venue to/from CMC

**What Happens on Delete:**

- Space reservation is automatically deleted

### Band/Performer Integration

Events connect to the Bands module:

- Many-to-many relationship with bands
- Performer pivot data: order, set_length
- Bands displayed on public event page
- Band genres shown on performer cards

### Notifications

| Event | Recipients | Content |
|-------|------------|---------|
| Event Created | Organizer | Event details |
| Event Published | Performers, stakeholders | Event title, date, venue |
| Event Updated | Performers, stakeholders | Changed fields |
| Event Cancelled | Performers, stakeholders | Cancellation notice |

### Google Calendar

Space reservations can sync to Google Calendar (configured in organization settings). The `google_calendar_event_id` tracks the linked calendar event.

### Tags/Genres

Events use the Spatie tags system:

- Tag events with genres
- Filterable in search
- Displayed on event cards and detail pages

### Media Library

Event posters use Spatie Media Library:

- Automatic image conversions
- Cloud storage (Cloudflare R2)
- Responsive image sizes

---

## Troubleshooting

### Event Not Appearing on Public Site

1. **Check publication status**: Is "Published At" set and in the past?
2. **Check visibility**: Is the event set to "Public"?
3. **Check event dates**: Is the event in the future (for "Upcoming" tab)?

### Cannot Publish Event

- Ensure the event has a title
- Verify you have permission (production manager or organizer)

### Performers Not Showing

- Ensure performers are attached to the event
- Check that band profiles are not deleted

### Space Reservation Not Created

- Verify the venue is set to CMC
- Check that the event has start/end times
- The sync runs automatically on create/update

### Poster Not Displaying

- Check file format (JPG, PNG, GIF, WebP)
- Verify file size is under 4MB
- Wait for image processing to complete

---

## Quick Reference

### URLs

| Page | URL |
|------|-----|
| Public events list | `/events` |
| Individual event | `/events/{id}` |
| Show tonight | `/show-tonight` |
| Staff events list | `/staff/events` |
| Create event | `/staff/events/create` |
| Edit event | `/staff/events/{id}/edit` |

### Keyboard Shortcuts (Filament)

| Shortcut | Action |
|----------|--------|
| `Ctrl/Cmd + S` | Save |
| `Escape` | Close modal |

### Event Checklist

Before publishing an event:

- [ ] Title is set
- [ ] Start date/time is correct
- [ ] Venue is selected
- [ ] Poster uploaded (recommended)
- [ ] Performers added with set lengths
- [ ] Ticket URL or event link provided
- [ ] Description written
- [ ] Visibility set appropriately
